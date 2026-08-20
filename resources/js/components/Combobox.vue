<script setup lang="ts" generic="T extends string | number | object">
import {
    Combobox,
    ComboboxInput,
    ComboboxButton,
    ComboboxOptions,
    ComboboxOption,
    TransitionRoot,
} from '@headlessui/vue';
import { Check, ChevronsUpDown, X } from 'lucide-vue-next';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { cn } from '@/lib/utils';

type Badge = { label: string; color: string };

type BaseComboboxProps<T> = {
    options: T[];
    displayFunction?: (option: T) => string;
    filterFunction?: (option: T, query: string) => boolean;
    badgeFunction?: (option: T) => Badge | null;
    valueKey?: keyof T;
    placeholder?: string;
    name?: string;
    nullable?: boolean;
};

type SingleProps<T> = BaseComboboxProps<T> & {
    multiple?: false;
    modelValue?: T | null;
    defaultValue?: T | null;
};

type MultiProps<T> = BaseComboboxProps<T> & {
    multiple: true;
    modelValue?: T[];
    defaultValue?: T[];
};

type ComboboxProps<T> = SingleProps<T> | MultiProps<T>;

const props = withDefaults(defineProps<ComboboxProps<T>>(), {
    multiple: false,
    placeholder: 'Select...',
});

const emit = defineEmits<{
    'update:modelValue': [value: T | T[] | null];
}>();

const query = ref('');
const internalValue = ref(
    props.defaultValue ?? (props.multiple ? [] : null),
) as any;

const proxyValue = computed({
    get() {
        return props.modelValue !== undefined
            ? props.modelValue
            : internalValue.value;
    },
    set(val) {
        internalValue.value = val as any;
        emit('update:modelValue', val as any);
    },
});

watch(
    () => props.defaultValue,
    (newVal) => {
        if (props.modelValue === undefined) {
            internalValue.value = newVal as any;
        }
    },
);

const getDisplayString = (option: any): string => {
    if (!option) return '';
    return props.displayFunction
        ? props.displayFunction(option)
        : String(option);
};

const removeOption = (option: T) => {
    if (Array.isArray(proxyValue.value)) {
        const newValue = proxyValue.value.filter(
            (item) => getOptionKey(item, 0) !== getOptionKey(option, 0),
        );
        proxyValue.value = newValue as any;
        emit('update:modelValue', newValue);
    }
};

const filteredOptions = computed(() => {
    const q = query.value.toLowerCase();
    if (q === '') return props.options;
    if (props.filterFunction)
        return props.options.filter((opt) =>
            props.filterFunction!(opt, query.value),
        );
    return props.options.filter((option) =>
        getDisplayString(option).toLowerCase().includes(q),
    );
});

const getOptionKey = (option: T, index: number) => {
    if (typeof option === 'object' && option !== null && 'id' in option)
        return (option as any).id;
    return index;
};

const getRawValue = (option: T) => {
    if (option && typeof option === 'object') {
        if (props.valueKey && props.valueKey in option) {
            return (option as any)[props.valueKey];
        }
        return JSON.stringify(option);
    }
    return String(option);
};

// ── Positioning & open-on-focus ──────────────────────────────────────────
const containerRef = ref<HTMLElement | null>(null);
const buttonRef = ref<{ $el: HTMLElement } | null>(null);
const dropdownStyle = ref<Record<string, string>>({});

const updatePosition = () => {
    if (!containerRef.value) return;
    const rect = containerRef.value.getBoundingClientRect();
    dropdownStyle.value = {
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
    };
};

onMounted(() => {
    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);
});

onUnmounted(() => {
    window.removeEventListener('scroll', updatePosition, true);
    window.removeEventListener('resize', updatePosition);
});
</script>

<template>
    <Combobox
        v-model="proxyValue"
        @update:model-value="
            (val: T | T[] | null) => emit('update:modelValue', val)
        "
        as="div"
        class="w-full"
        :multiple="multiple"
        :nullable="nullable"
        :by="valueKey ? String(valueKey) : undefined"
        v-slot="{ open }"
    >
        <template v-if="name">
            <template v-if="multiple && Array.isArray(proxyValue)">
                <input
                    v-for="(option, index) in proxyValue"
                    type="hidden"
                    :name="`${name}[]`"
                    :value="getRawValue(option)"
                    :key="getOptionKey(option, index)"
                />
            </template>
            <template v-else-if="proxyValue">
                <input
                    type="hidden"
                    :name="name"
                    :value="getRawValue(proxyValue)"
                />
            </template>
        </template>
        <div class="relative" ref="containerRef">
            <div
                :class="
                    cn(
                        'relative flex min-h-9 w-full flex-wrap items-center gap-1 rounded-md border border-input bg-transparent px-3 py-1 shadow-xs transition-shadow dark:bg-input/30',
                        'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                    )
                "
            >
                <template v-if="multiple && Array.isArray(proxyValue)">
                    <div
                        v-for="option in proxyValue"
                        :key="getOptionKey(option, 0)"
                        class="flex items-center gap-1 rounded bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground"
                    >
                        {{ getDisplayString(option) }}
                        <button
                            type="button"
                            @click.stop="removeOption(option)"
                            class="rounded-full outline-none hover:bg-muted-foreground/20"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </div>
                </template>

                <ComboboxInput
                    :class="
                        cn(
                            'min-w-12.5 flex-1 border-none bg-transparent p-0 text-sm outline-none placeholder:text-muted-foreground focus:ring-0',
                            !multiple && 'h-full w-full',
                        )
                    "
                    @change="query = $event.target.value"
                    @focus="
                        () => {
                            updatePosition();
                            if (!open) buttonRef?.$el?.click();
                        }
                    "
                    :display-value="
                        (val: unknown) =>
                            multiple ? '' : getDisplayString(val as T)
                    "
                    :placeholder="
                        !multiple ||
                        (Array.isArray(proxyValue) && proxyValue.length === 0)
                            ? placeholder
                            : ''
                    "
                />

                <ComboboxButton
                    ref="buttonRef"
                    class="ml-auto flex cursor-pointer items-center pr-1"
                    @click="updatePosition"
                >
                    <ChevronsUpDown
                        class="h-4 w-4 text-muted-foreground/50"
                        aria-hidden="true"
                    />
                </ComboboxButton>
            </div>

            <Teleport to="body">
                <TransitionRoot
                    leave="transition ease-in duration-100"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                    @after-leave="query = ''"
                >
                    <ComboboxOptions
                        class="pointer-events-auto fixed z-9999 max-h-60 overflow-auto rounded-md border border-input bg-white p-1 text-sm shadow-md outline-none dark:bg-neutral-900"
                        :style="dropdownStyle"
                        @pointerdown.stop
                    >
                        <div
                            v-if="filteredOptions.length === 0"
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No results found.
                        </div>

                        <ComboboxOption
                            v-for="(option, index) in filteredOptions"
                            :key="getOptionKey(option, index)"
                            :value="option"
                            v-slot="{ selected, active }"
                        >
                            <li
                                :class="
                                    cn(
                                        'relative flex w-full cursor-pointer items-center rounded-sm py-1.5 pr-2 pl-8 text-sm transition-colors outline-none select-none',
                                        active || selected
                                            ? 'bg-accent text-accent-foreground'
                                            : 'text-foreground',
                                    )
                                "
                            >
                                <span
                                    v-if="selected"
                                    class="absolute left-2 flex h-3.5 w-3.5 items-center justify-center"
                                >
                                    <Check class="h-4 w-4" />
                                </span>

                                <span
                                    :class="
                                        cn(
                                            'block truncate',
                                            selected
                                                ? 'font-medium'
                                                : 'font-normal',
                                        )
                                    "
                                >
                                    {{ getDisplayString(option) }}
                                </span>

                                <template v-if="badgeFunction">
                                    <span
                                        v-if="
                                            badgeFunction(
                                                option,
                                            ) as Badge | null
                                        "
                                        class="ml-auto shrink-0 pl-3"
                                    >
                                        <span
                                            class="inline-block h-2 w-2 rounded-full"
                                            :style="{
                                                backgroundColor: (
                                                    badgeFunction(
                                                        option,
                                                    ) as Badge
                                                ).color,
                                            }"
                                        ></span>
                                    </span>
                                </template>
                            </li>
                        </ComboboxOption>
                    </ComboboxOptions>
                </TransitionRoot>
            </Teleport>
        </div>
    </Combobox>
</template>
