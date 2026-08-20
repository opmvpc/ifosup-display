import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';

/**
 * Endpoints Fortify de la double authentification.
 *
 * Ils sont déclarés en dur plutôt qu'importés de `@/routes/two-factor` : ce module
 * est généré par Wayfinder à partir des routes existantes, or la fonctionnalité est
 * désactivée (`'features' => []` dans `config/fortify.php`, aucun serveur mail).
 * Le module n'est donc pas généré et l'import cassait `vue-tsc`.
 *
 * Ces chemins sont ceux que Fortify enregistre : réactiver la fonctionnalité suffit
 * à les rendre fonctionnels, sans rien changer ici.
 */
type FormAction = { action: string; method: 'post' };

const postForm = (action: string): FormAction => ({ action, method: 'post' });

export const twoFactorRoutes = {
    qrCode: { url: (): string => '/user/two-factor-qr-code' },
    secretKey: { url: (): string => '/user/two-factor-secret-key' },
    recoveryCodes: { url: (): string => '/user/two-factor-recovery-codes' },
    regenerateRecoveryCodes: {
        url: (): string => '/user/two-factor-recovery-codes',
        form: (): FormAction => postForm('/user/two-factor-recovery-codes'),
    },
    confirm: {
        url: (): string => '/user/confirmed-two-factor-authentication',
        form: (): FormAction =>
            postForm('/user/confirmed-two-factor-authentication'),
    },
} as const;

const { qrCode, recoveryCodes, secretKey } = twoFactorRoutes;

export type UseTwoFactorAuthReturn = {
    qrCodeSvg: Ref<string | null>;
    manualSetupKey: Ref<string | null>;
    recoveryCodesList: Ref<string[]>;
    errors: Ref<string[]>;
    hasSetupData: ComputedRef<boolean>;
    clearSetupData: () => void;
    clearErrors: () => void;
    clearTwoFactorAuthData: () => void;
    fetchQrCode: () => Promise<void>;
    fetchSetupKey: () => Promise<void>;
    fetchSetupData: () => Promise<void>;
    fetchRecoveryCodes: () => Promise<void>;
};

const fetchJson = async <T>(url: string): Promise<T> => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Failed to fetch: ${response.status}`);
    }

    return response.json();
};

const errors = ref<string[]>([]);
const manualSetupKey = ref<string | null>(null);
const qrCodeSvg = ref<string | null>(null);
const recoveryCodesList = ref<string[]>([]);

const hasSetupData = computed<boolean>(
    () => qrCodeSvg.value !== null && manualSetupKey.value !== null,
);

export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => {
    const fetchQrCode = async (): Promise<void> => {
        try {
            const { svg } = await fetchJson<{ svg: string; url: string }>(
                qrCode.url(),
            );

            qrCodeSvg.value = svg;
        } catch {
            errors.value.push('Failed to fetch QR code');
            qrCodeSvg.value = null;
        }
    };

    const fetchSetupKey = async (): Promise<void> => {
        try {
            const { secretKey: key } = await fetchJson<{ secretKey: string }>(
                secretKey.url(),
            );

            manualSetupKey.value = key;
        } catch {
            errors.value.push('Failed to fetch a setup key');
            manualSetupKey.value = null;
        }
    };

    const clearSetupData = (): void => {
        manualSetupKey.value = null;
        qrCodeSvg.value = null;
        clearErrors();
    };

    const clearErrors = (): void => {
        errors.value = [];
    };

    const clearTwoFactorAuthData = (): void => {
        clearSetupData();
        clearErrors();
        recoveryCodesList.value = [];
    };

    const fetchRecoveryCodes = async (): Promise<void> => {
        try {
            clearErrors();
            recoveryCodesList.value = await fetchJson<string[]>(
                recoveryCodes.url(),
            );
        } catch {
            errors.value.push('Failed to fetch recovery codes');
            recoveryCodesList.value = [];
        }
    };

    const fetchSetupData = async (): Promise<void> => {
        try {
            clearErrors();
            await Promise.all([fetchQrCode(), fetchSetupKey()]);
        } catch {
            qrCodeSvg.value = null;
            manualSetupKey.value = null;
        }
    };

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        errors,
        hasSetupData,
        clearSetupData,
        clearErrors,
        clearTwoFactorAuthData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
