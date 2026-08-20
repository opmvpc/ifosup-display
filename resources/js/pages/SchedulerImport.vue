<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    BookOpen,
    CalendarRange,
    CheckCircle2,
    ChevronLeft,
    DoorOpen,
    Eraser,
    FileSpreadsheet,
    FileUp,
    RefreshCcw,
    ShieldAlert,
    Trash2,
    UploadCloud,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import actions from '@/actions/App/Http/Controllers/SchedulerImportController';
import InputError from '@/components/InputError.vue';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{ hasPendingFile: boolean; justUploaded: boolean }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Planning', href: '/scheduler' },
    { title: 'Import Excel', href: actions.index().url },
];

type Step =
    | 'upload'
    | 'pending'
    | 'parsing'
    | 'summary'
    | 'importing'
    | 'success';
const step = ref<Step>('upload');

watch(
    () => [props.hasPendingFile, props.justUploaded] as const,
    ([hasPending, justUploaded]) => {
        if (justUploaded) {
            startPreview();
        } else if (hasPending) {
            step.value = 'pending';
        } else {
            step.value = 'upload';
        }
    },
    { immediate: true },
);

interface ConflictEntry {
    date: string;
    period: string;
    local: string;
    course_new: string;
    course_current: string | null;
}

interface BreakdownEntry {
    room: string;
    course: string;
    count: number;
    conflict_count: number;
}

interface PreviewSummary {
    total: number;
    start_year: number;
    date_from: string | null;
    date_to: string | null;
    assignments_in_range: number;
    existing_rooms: string[];
    new_rooms: string[];
    known_courses: { code: string; name: string }[];
    unknown_courses: string[];
    conflicts: ConflictEntry[];
    breakdown: BreakdownEntry[];
    room_counts: Record<string, number>;
    course_counts: Record<string, number>;
}

const summary = ref<PreviewSummary | null>(null);
const previewError = ref<string | null>(null);

const selectedCourses = ref<string[]>([]);
const purgePeriod = ref(false);

interface ImportResult {
    imported: number;
    replaced: number;
    purged: number;
}
const importResult = ref<ImportResult | null>(null);
const importError = ref<string | null>(null);

// ─── Upload form ──────────────────────────────────────────────────────────────

const currentYear = new Date().getFullYear();
// Default to current school year: if we're past August, start year = current year, else current year - 1
const defaultStartYear =
    new Date().getMonth() >= 8 ? currentYear : currentYear - 1;
const uploadForm = useForm({
    file: null as File | null,
    start_year: defaultStartYear,
});

const isDragging = ref(false);
const selectedFileName = ref<string | null>(null);

// School year picker: list of years around the default
const yearOptions = computed(() => {
    const years: number[] = [];
    for (let y = currentYear - 5; y <= currentYear + 4; y++) years.push(y);
    return years;
});

function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    uploadForm.file = file;
    selectedFileName.value = file?.name ?? null;
}

function onFileDrop(e: DragEvent) {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0] ?? null;
    if (!file) return;
    uploadForm.file = file;
    selectedFileName.value = file.name;
}

function submitUpload() {
    uploadForm.post(actions.upload.url(), { forceFormData: true });
}

// ─── CSRF helper ──────────────────────────────────────────────────────────────

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function apiPost<T>(
    url: string,
    body: Record<string, unknown>,
): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(body),
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error ?? 'Une erreur est survenue.');
    return json as T;
}

// ─── Preview ──────────────────────────────────────────────────────────────────

async function startPreview() {
    step.value = 'parsing';
    previewError.value = null;
    summary.value = null;

    try {
        const result = await apiPost<PreviewSummary>(actions.preview.url(), {});
        summary.value = result;
        selectedCourses.value = result.known_courses.map((c) => c.code);
        step.value = 'summary';
    } catch (e) {
        previewError.value =
            e instanceof Error ? e.message : 'Erreur inconnue.';
        step.value = props.hasPendingFile ? 'pending' : 'upload';
    }
}

// ─── Discard ──────────────────────────────────────────────────────────────────

function discardFile() {
    router.delete(actions.discard.url(), { preserveScroll: true });
}

// ─── Selection helpers ────────────────────────────────────────────────────────

function toggleCourse(code: string, checked: boolean) {
    selectedCourses.value = checked
        ? [...selectedCourses.value, code]
        : selectedCourses.value.filter((c) => c !== code);
}

function toggleAllCourses(checked: boolean) {
    if (!summary.value) return;
    selectedCourses.value = checked
        ? summary.value.known_courses.map((c) => c.code)
        : [];
}

function compareRooms(a: string, b: string): number {
    const isInt = (s: string) => /^-?\d+$/.test(s);
    const na = parseInt(a, 10),
        nb = parseInt(b, 10);
    const group = (s: string, n: number) => (!isInt(s) ? 2 : n < 0 ? 0 : 1);
    const ga = group(a, na),
        gb = group(b, nb);
    if (ga !== gb) return ga - gb;
    if (ga === 0) return Math.abs(na) - Math.abs(nb); // -1, -2, -3…
    if (ga === 1) return na - nb; // 1, 2, 3…
    return a.localeCompare(b); // alphabétique
}

const allRooms = computed(() => {
    if (!summary.value) return [];
    return [...summary.value.existing_rooms, ...summary.value.new_rooms]
        .map(String)
        .sort(compareRooms);
});

const allCoursesSelected = computed(
    () =>
        !!summary.value &&
        summary.value.known_courses.length > 0 &&
        summary.value.known_courses.every((c) =>
            selectedCourses.value.includes(c.code),
        ),
);
const someCoursesSelected = computed(
    () => selectedCourses.value.length > 0 && !allCoursesSelected.value,
);

const selectedTotal = computed(() => {
    if (!summary.value) return { new: 0, replaced: 0 };
    return summary.value.breakdown.reduce(
        (acc, b) => {
            if (selectedCourses.value.includes(b.course)) {
                acc.new += b.count - b.conflict_count;
                acc.replaced += b.conflict_count;
            }
            return acc;
        },
        { new: 0, replaced: 0 },
    );
});

// When purging, all selected entries become creations (no existing ones to replace)
const effectiveTotal = computed(() => {
    if (purgePeriod.value) {
        return {
            new: selectedTotal.value.new + selectedTotal.value.replaced,
            replaced: 0,
        };
    }
    return selectedTotal.value;
});

const skippedCount = computed(() => {
    if (!summary.value) return 0;
    return (
        summary.value.total -
        selectedTotal.value.new -
        selectedTotal.value.replaced
    );
});

const selectedConflicts = computed(() => {
    if (!summary.value) return [];
    return summary.value.conflicts.filter((c) =>
        selectedCourses.value.includes(c.course_new),
    );
});

function formatDate(isoDate: string | null): string {
    if (!isoDate) return '—';
    const parts = isoDate.split('-');
    if (parts.length !== 3) return isoDate;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

// ─── Execute import ───────────────────────────────────────────────────────────

async function executeImport() {
    step.value = 'importing';
    importError.value = null;

    try {
        const result = await apiPost<ImportResult>(
            actions.executeImport.url(),
            {
                selected_rooms: allRooms.value,
                selected_courses: selectedCourses.value,
                purge_period: purgePeriod.value,
            },
        );
        importResult.value = result;
        step.value = 'success';
    } catch (e) {
        importError.value = e instanceof Error ? e.message : 'Erreur inconnue.';
        step.value = 'summary';
    }
}

function restart() {
    importResult.value = null;
    summary.value = null;
    purgePeriod.value = false;
    step.value = 'upload';
}

const showAllUnknownCourses = ref(false);

const periodLabels: Record<string, string> = {
    morning: 'Matin',
    afternoon: 'Après-midi',
    evening: 'Soir',
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-6">
            <div>
                <h1 class="text-2xl font-bold">Import depuis Excel</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Importez les attributions de cours depuis un fichier
                    planning Excel.
                </p>
            </div>

            <!-- ── upload ─────────────────────────────────────────────── -->
            <template v-if="step === 'upload'">
                <div class="rounded-xl border p-6">
                    <h2 class="mb-5 text-lg font-semibold">
                        Choisir un fichier
                    </h2>
                    <form @submit.prevent="submitUpload" class="space-y-5">
                        <!-- File drop zone -->
                        <div class="grid gap-2">
                            <label
                                for="file"
                                class="group relative flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed px-6 py-10 transition-colors"
                                :class="
                                    isDragging
                                        ? 'border-primary bg-primary/5'
                                        : selectedFileName
                                          ? 'border-green-400 bg-green-50/40 dark:border-green-700 dark:bg-green-950/10'
                                          : 'border-border hover:border-primary/50 hover:bg-muted/30'
                                "
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="onFileDrop"
                            >
                                <div
                                    class="flex size-12 items-center justify-center rounded-full transition-colors"
                                    :class="
                                        selectedFileName
                                            ? 'bg-green-100 dark:bg-green-900/30'
                                            : 'bg-muted group-hover:bg-muted/70'
                                    "
                                >
                                    <FileSpreadsheet
                                        class="size-6 transition-colors"
                                        :class="
                                            selectedFileName
                                                ? 'text-green-600 dark:text-green-400'
                                                : 'text-muted-foreground'
                                        "
                                    />
                                </div>
                                <div class="text-center">
                                    <p
                                        v-if="selectedFileName"
                                        class="text-sm font-medium text-green-700 dark:text-green-400"
                                    >
                                        {{ selectedFileName }}
                                    </p>
                                    <template v-else>
                                        <p class="text-sm font-medium">
                                            Déposez votre fichier ici
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            ou cliquez pour parcourir — .xlsx,
                                            .xls
                                        </p>
                                    </template>
                                </div>
                                <input
                                    id="file"
                                    type="file"
                                    accept=".xlsx,.xls"
                                    class="sr-only"
                                    @change="onFileChange"
                                    required
                                />
                            </label>
                            <InputError :message="uploadForm.errors.file" />
                        </div>

                        <!-- School year picker -->
                        <div class="grid gap-2">
                            <Label for="start_year">Année scolaire</Label>
                            <select
                                id="start_year"
                                v-model="uploadForm.start_year"
                                class="h-9 w-fit rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm ring-offset-background focus:ring-1 focus:ring-ring focus:outline-none"
                            >
                                <option
                                    v-for="y in yearOptions"
                                    :key="y"
                                    :value="y"
                                >
                                    {{ y }}–{{ y + 1 }}
                                </option>
                            </select>
                            <InputError
                                :message="uploadForm.errors.start_year"
                            />
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="
                                    uploadForm.processing || !uploadForm.file
                                "
                            >
                                <UploadCloud class="mr-2 size-4" />
                                {{
                                    uploadForm.processing
                                        ? 'Chargement…'
                                        : 'Charger le fichier'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </template>

            <!-- ── pending ────────────────────────────────────────────── -->
            <template v-else-if="step === 'pending'">
                <Alert>
                    <FileUp class="size-4" />
                    <AlertTitle>Fichier en attente</AlertTitle>
                    <AlertDescription>
                        Un fichier uploadé lors d'une session précédente est
                        disponible.
                    </AlertDescription>
                </Alert>
                <Alert v-if="previewError" variant="destructive">
                    <AlertCircle class="size-4" />
                    <AlertTitle>Erreur</AlertTitle>
                    <AlertDescription>{{ previewError }}</AlertDescription>
                </Alert>
                <div class="flex flex-row-reverse gap-3">
                    <Button @click="startPreview">
                        <RefreshCcw class="mr-2 size-4" />Ré-analyser
                    </Button>
                    <Button variant="outline" @click="discardFile">
                        <Trash2 class="mr-2 size-4" />Ignorer et uploader un
                        autre fichier
                    </Button>
                </div>
            </template>

            <!-- ── parsing ────────────────────────────────────────────── -->
            <template v-else-if="step === 'parsing'">
                <div class="flex flex-col items-center gap-4 py-16">
                    <Spinner class="size-10" />
                    <p class="text-muted-foreground">
                        Analyse du fichier en cours…
                    </p>
                </div>
            </template>

            <!-- ── summary ────────────────────────────────────────────── -->
            <template v-else-if="step === 'summary' && summary">
                <!-- Scope header -->
                <div
                    class="flex items-start justify-between rounded-xl border px-5 py-4"
                >
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <CheckCircle2 class="size-4 text-green-500" />
                            <h2 class="font-semibold">
                                {{ summary.total.toLocaleString('fr-FR') }}
                                attributions analysées
                            </h2>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Année scolaire {{ summary.start_year }}–{{
                                summary.start_year + 1
                            }}
                        </p>
                        <div class="flex items-center gap-1.5">
                            <CalendarRange
                                class="size-3.5 text-muted-foreground"
                            />
                            <span class="font-mono text-sm">
                                {{ formatDate(summary.date_from) }} →
                                {{ formatDate(summary.date_to) }}
                            </span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-destructive"
                        @click="discardFile"
                    >
                        <ChevronLeft class="size-3.5" /> Annuler
                    </button>
                </div>

                <!-- Impact stats -->
                <div class="grid grid-cols-3 gap-3">
                    <div
                        class="rounded-xl border border-green-200 bg-green-50 p-4 text-center dark:border-green-800 dark:bg-green-950/20"
                    >
                        <p
                            class="text-3xl font-bold text-green-700 tabular-nums dark:text-green-400"
                        >
                            {{
                                (purgePeriod
                                    ? effectiveTotal.new
                                    : selectedTotal.new
                                ).toLocaleString('fr-FR')
                            }}
                        </p>
                        <p
                            class="mt-1 text-sm font-medium text-green-600 dark:text-green-500"
                        >
                            Créations
                        </p>
                    </div>
                    <div
                        class="rounded-xl border p-4 text-center transition-colors"
                        :class="
                            purgePeriod
                                ? 'border-border bg-muted/20'
                                : 'border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-950/20'
                        "
                    >
                        <p
                            class="text-3xl font-bold tabular-nums transition-colors"
                            :class="
                                purgePeriod
                                    ? 'text-muted-foreground/40 line-through'
                                    : 'text-orange-700 dark:text-orange-400'
                            "
                        >
                            {{ selectedTotal.replaced.toLocaleString('fr-FR') }}
                        </p>
                        <p
                            class="mt-1 text-sm font-medium transition-colors"
                            :class="
                                purgePeriod
                                    ? 'text-muted-foreground/40'
                                    : 'text-orange-600 dark:text-orange-500'
                            "
                        >
                            Remplacements
                        </p>
                        <p
                            v-if="!purgePeriod && selectedTotal.replaced > 0"
                            class="mt-0.5 text-xs text-orange-500"
                        >
                            écraseront l'existant
                        </p>
                        <p
                            v-if="purgePeriod"
                            class="mt-0.5 text-xs text-muted-foreground/40"
                        >
                            annulés par la purge
                        </p>
                    </div>
                    <div class="rounded-xl border bg-muted/20 p-4 text-center">
                        <p
                            class="text-3xl font-bold text-muted-foreground tabular-nums"
                        >
                            {{ skippedCount.toLocaleString('fr-FR') }}
                        </p>
                        <p
                            class="mt-1 text-sm font-medium text-muted-foreground"
                        >
                            Ignorées
                        </p>
                    </div>
                </div>

                <!-- Conflict warning (visible when conflicts exist and no purge) -->
                <div
                    v-if="!purgePeriod && selectedConflicts.length > 0"
                    class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 dark:border-orange-800 dark:bg-orange-950/20"
                >
                    <div class="flex items-start gap-3">
                        <ShieldAlert
                            class="mt-0.5 size-5 shrink-0 text-orange-600"
                        />
                        <div>
                            <p
                                class="font-semibold text-orange-800 dark:text-orange-300"
                            >
                                {{ selectedConflicts.length }} créneau(x) seront
                                remplacés
                            </p>
                            <p
                                class="mt-0.5 text-sm text-orange-700 dark:text-orange-400"
                            >
                                Les attributions existantes à ces horaires
                                seront <strong>écrasées</strong> sans retour
                                possible. Activez la purge ci-dessous pour
                                nettoyer toute la période d'abord.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Purge option (danger zone) -->
                <div
                    class="overflow-hidden rounded-xl border-2 transition-all"
                    :class="
                        purgePeriod
                            ? 'border-red-400 dark:border-red-700'
                            : 'border-red-100 dark:border-red-900/40'
                    "
                >
                    <div
                        class="flex items-center justify-between px-4 py-3 transition-colors"
                        :class="
                            purgePeriod
                                ? 'bg-red-100 dark:bg-red-950/50'
                                : 'bg-red-50/40 dark:bg-red-950/10'
                        "
                    >
                        <div class="flex items-center gap-2">
                            <Eraser
                                class="size-4 transition-colors"
                                :class="
                                    purgePeriod
                                        ? 'text-red-600'
                                        : 'text-red-300 dark:text-red-800'
                                "
                            />
                            <span
                                class="text-sm font-semibold transition-colors"
                                :class="
                                    purgePeriod
                                        ? 'text-red-800 dark:text-red-200'
                                        : 'text-red-600/50 dark:text-red-500/50'
                                "
                            >
                                Purger la période avant l'import
                            </span>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                class="size-4 cursor-pointer accent-red-600"
                                :checked="purgePeriod"
                                @change="
                                    purgePeriod = (
                                        $event.target as HTMLInputElement
                                    ).checked
                                "
                            />
                            <span
                                class="text-sm font-medium transition-colors"
                                :class="
                                    purgePeriod
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ purgePeriod ? 'Activé' : 'Désactivé' }}
                            </span>
                        </label>
                    </div>
                    <div class="space-y-2 px-4 py-3 text-sm">
                        <p class="text-muted-foreground">
                            Supprime
                            <span class="font-semibold text-red-600"
                                >toutes</span
                            >
                            les attributions du
                            <code
                                class="rounded bg-muted px-1 py-0.5 text-xs"
                                >{{ formatDate(summary.date_from) }}</code
                            >
                            au
                            <code
                                class="rounded bg-muted px-1 py-0.5 text-xs"
                                >{{ formatDate(summary.date_to) }}</code
                            >
                            avant d'importer, sans retour en arrière possible.
                        </p>
                        <div
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium"
                            :class="
                                summary.assignments_in_range > 0
                                    ? 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-400'
                                    : 'bg-muted/40 text-muted-foreground'
                            "
                        >
                            <AlertCircle class="size-4 shrink-0" />
                            <span v-if="summary.assignments_in_range > 0">
                                {{
                                    summary.assignments_in_range.toLocaleString(
                                        'fr-FR',
                                    )
                                }}
                                enregistrement(s) existant(s) dans cette période
                                seront définitivement supprimés.
                            </span>
                            <span v-else
                                >Aucun enregistrement existant dans cette
                                période.</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Locaux -->
                <div class="rounded-xl border">
                    <div class="border-b px-4 py-3">
                        <div class="flex items-center gap-2">
                            <DoorOpen class="size-4 text-muted-foreground" />
                            <span class="font-semibold">Locaux</span>
                            <span
                                class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground tabular-nums"
                            >
                                {{ allRooms.length }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-x-1 gap-y-1 px-4 py-3">
                        <template v-for="(room, i) in allRooms" :key="room">
                            <span
                                v-if="i > 0"
                                class="self-center text-muted-foreground"
                                >,</span
                            >
                            <span class="text-sm font-medium">{{ room }}</span>
                        </template>
                    </div>
                </div>

                <!-- Cours -->
                <div class="rounded-xl border">
                    <div
                        class="flex items-center justify-between border-b px-4 py-3"
                    >
                        <div class="flex items-center gap-2">
                            <BookOpen class="size-4 text-muted-foreground" />
                            <span class="font-semibold">Cours</span>
                            <span
                                class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground tabular-nums"
                            >
                                {{ selectedCourses.length }}/{{
                                    summary.known_courses.length
                                }}
                            </span>
                        </div>
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="size-4 cursor-pointer rounded accent-primary"
                                :checked="allCoursesSelected"
                                :indeterminate="someCoursesSelected"
                                @change="
                                    toggleAllCourses(
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            Tout cocher
                        </label>
                    </div>

                    <div class="divide-y">
                        <label
                            v-for="course in summary.known_courses"
                            :key="course.code"
                            class="flex cursor-pointer items-center gap-3 px-4 py-2.5 transition-colors hover:bg-muted/30"
                        >
                            <input
                                type="checkbox"
                                class="size-4 cursor-pointer rounded accent-primary"
                                :checked="selectedCourses.includes(course.code)"
                                @change="
                                    toggleCourse(
                                        course.code,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <span
                                class="w-20 shrink-0 font-mono text-sm font-semibold"
                                >{{ course.code }}</span
                            >
                            <span
                                class="flex-1 text-sm text-muted-foreground"
                                >{{ course.name }}</span
                            >
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{
                                    (
                                        summary.course_counts[course.code] ?? 0
                                    ).toLocaleString('fr-FR')
                                }}
                                cours
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Cours ignorés -->
                <div
                    v-if="summary.unknown_courses.length > 0"
                    class="rounded-xl border border-dashed border-red-200 dark:border-red-900/50"
                >
                    <div
                        class="flex items-center gap-2 border-b border-dashed border-red-200 px-4 py-3 dark:border-red-900/50"
                    >
                        <BookOpen class="size-4 text-red-400" />
                        <span
                            class="font-semibold text-red-600 dark:text-red-400"
                            >Cours ignorés</span
                        >
                        <span
                            class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-500 tabular-nums dark:bg-red-950/40 dark:text-red-400"
                        >
                            {{ summary.unknown_courses.length }}
                        </span>
                        <span class="text-xs text-muted-foreground"
                            >— N'existent pas dans la base de données</span
                        >
                    </div>

                    <div class="relative">
                        <div
                            class="divide-y divide-red-100 dark:divide-red-900/30"
                            :class="
                                !showAllUnknownCourses &&
                                summary.unknown_courses.length > 10
                                    ? 'max-h-[calc(10*2.625rem)] overflow-hidden'
                                    : ''
                            "
                        >
                            <div
                                v-for="course in summary.unknown_courses"
                                :key="course"
                                class="flex items-center gap-3 px-4 py-2.5"
                            >
                                <div
                                    class="size-4 shrink-0 rounded border border-dashed border-red-300"
                                />
                                <span
                                    class="w-20 shrink-0 font-mono text-sm text-red-500"
                                    >{{ course }}</span
                                >
                                <span class="flex-1" />
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    {{
                                        (
                                            summary.course_counts[course] ?? 0
                                        ).toLocaleString('fr-FR')
                                    }}
                                    cours
                                </span>
                            </div>
                        </div>

                        <!-- Fade + expand button -->
                        <div
                            v-if="
                                !showAllUnknownCourses &&
                                summary.unknown_courses.length > 10
                            "
                            class="absolute right-0 bottom-0 left-0 flex flex-col items-center justify-end pt-16 pb-3"
                            style="
                                background: linear-gradient(
                                    to bottom,
                                    transparent,
                                    var(--color-background, white) 70%
                                );
                            "
                        >
                            <button
                                type="button"
                                class="rounded-full border border-red-200 bg-white px-4 py-1 text-xs font-medium text-red-600 shadow-sm transition hover:bg-red-50 dark:border-red-800 dark:bg-background dark:text-red-400 dark:hover:bg-red-950/30"
                                @click="showAllUnknownCourses = true"
                            >
                                Voir les
                                {{ summary.unknown_courses.length - 10 }} autres
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Conflicts detail (scrollable, hidden when purge active) -->
                <div
                    v-if="!purgePeriod && selectedConflicts.length > 0"
                    class="overflow-hidden rounded-xl border border-orange-200 dark:border-orange-800"
                >
                    <div
                        class="flex items-center justify-between border-b border-orange-200 bg-orange-50 px-4 py-3 dark:border-orange-800 dark:bg-orange-950/30"
                    >
                        <span
                            class="text-sm font-semibold text-orange-800 dark:text-orange-300"
                        >
                            Détail des remplacements
                        </span>
                        <span
                            class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900 dark:text-orange-300"
                        >
                            {{ selectedConflicts.length }} créneau(x)
                        </span>
                    </div>
                    <div class="max-h-52 overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0">
                                <tr
                                    class="border-b bg-orange-50/90 text-left backdrop-blur dark:bg-orange-950/30"
                                >
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-orange-700 dark:text-orange-400"
                                    >
                                        Date
                                    </th>
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-orange-700 dark:text-orange-400"
                                    >
                                        Période
                                    </th>
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-orange-700 dark:text-orange-400"
                                    >
                                        Local
                                    </th>
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-orange-700 dark:text-orange-400"
                                    >
                                        Actuel
                                    </th>
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-orange-700 dark:text-orange-400"
                                    >
                                        → Remplacé par
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-orange-100 dark:divide-orange-900"
                            >
                                <tr
                                    v-for="(c, i) in selectedConflicts"
                                    :key="i"
                                    class="hover:bg-orange-50/50 dark:hover:bg-orange-950/20"
                                >
                                    <td class="px-4 py-1.5 tabular-nums">
                                        {{ c.date }}
                                    </td>
                                    <td class="px-4 py-1.5">
                                        {{ periodLabels[c.period] ?? c.period }}
                                    </td>
                                    <td class="px-4 py-1.5">{{ c.local }}</td>
                                    <td
                                        class="px-4 py-1.5 font-mono text-orange-600 line-through opacity-75"
                                    >
                                        {{ c.course_current ?? '—' }}
                                    </td>
                                    <td
                                        class="px-4 py-1.5 font-mono font-semibold"
                                    >
                                        {{ c.course_new }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Action bar -->
                <div class="rounded-xl border px-5 py-5">
                    <!-- Purge active summary -->
                    <div
                        v-if="purgePeriod && summary.assignments_in_range > 0"
                        class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:bg-red-950/30 dark:text-red-400"
                    >
                        <AlertCircle class="size-4 shrink-0" />
                        {{
                            summary.assignments_in_range.toLocaleString('fr-FR')
                        }}
                        enregistrement(s) supprimés en premier (purge).
                    </div>

                    <Alert
                        v-if="importError"
                        variant="destructive"
                        class="mb-4"
                    >
                        <AlertCircle class="size-4" />
                        <AlertTitle>Erreur lors de l'import</AlertTitle>
                        <AlertDescription>{{ importError }}</AlertDescription>
                    </Alert>

                    <div
                        class="flex flex-row-reverse flex-wrap items-center gap-3"
                    >
                        <Button
                            @click="executeImport"
                            :disabled="
                                effectiveTotal.new + effectiveTotal.replaced ===
                                0
                            "
                            :variant="
                                (purgePeriod &&
                                    summary.assignments_in_range > 0) ||
                                (!purgePeriod && selectedConflicts.length > 0)
                                    ? 'destructive'
                                    : 'default'
                            "
                        >
                            Importer
                            {{
                                (
                                    effectiveTotal.new + effectiveTotal.replaced
                                ).toLocaleString('fr-FR')
                            }}
                            attribution(s)
                            <span
                                v-if="purgePeriod"
                                class="ml-1 text-xs opacity-75"
                                >+ purge</span
                            >
                        </Button>
                        <p
                            v-if="
                                effectiveTotal.new + effectiveTotal.replaced ===
                                0
                            "
                            class="text-sm text-muted-foreground"
                        >
                            Sélectionnez au moins un local et un cours.
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            <template v-if="purgePeriod">
                                {{ effectiveTotal.new.toLocaleString('fr-FR') }}
                                créations
                                <template
                                    v-if="summary.assignments_in_range > 0"
                                >
                                    + purge de
                                    {{
                                        summary.assignments_in_range.toLocaleString(
                                            'fr-FR',
                                        )
                                    }}
                                    enregistements.
                                </template>
                            </template>
                            <template v-else>
                                {{ selectedTotal.new.toLocaleString('fr-FR') }}
                                nouvelles ·
                                {{
                                    selectedTotal.replaced.toLocaleString(
                                        'fr-FR',
                                    )
                                }}
                                remplacement(s)
                            </template>
                        </p>
                    </div>
                </div>
            </template>

            <!-- ── importing ──────────────────────────────────────────── -->
            <template v-else-if="step === 'importing'">
                <div class="flex flex-col items-center gap-4 py-16">
                    <Spinner class="size-10" />
                    <p class="text-muted-foreground">Import en cours…</p>
                </div>
            </template>

            <!-- ── success ────────────────────────────────────────────── -->
            <template v-else-if="step === 'success' && importResult">
                <Alert>
                    <CheckCircle2 class="size-4" />
                    <AlertTitle>Import terminé !</AlertTitle>
                    <AlertDescription class="space-y-1">
                        <p v-if="importResult.purged > 0">
                            <span class="font-medium">{{
                                importResult.purged.toLocaleString('fr-FR')
                            }}</span>
                            enregistrement(s) purgé(s) avant import.
                        </p>
                        <p>
                            <span class="font-medium">{{
                                importResult.imported.toLocaleString('fr-FR')
                            }}</span>
                            attribution(s) créée(s)<template
                                v-if="importResult.replaced > 0"
                                >,
                                <span class="font-medium">{{
                                    importResult.replaced.toLocaleString(
                                        'fr-FR',
                                    )
                                }}</span>
                                remplacée(s)</template
                            >.
                        </p>
                    </AlertDescription>
                </Alert>
                <div class="flex gap-3">
                    <Button as="a" href="/scheduler">Voir le planning</Button>
                    <Button variant="outline" @click="restart"
                        >Importer un autre fichier</Button
                    >
                </div>
            </template>
        </div>
    </AppLayout>
</template>
