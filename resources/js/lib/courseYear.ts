const COURSE_YEAR_LABELS: Record<number, string> = {
    1: '1ère année',
    2: '2e année',
    3: '3e année',
};

export function courseYearLabel(
    year: number | null | undefined,
): string | null {
    if (year == null) return null;
    return COURSE_YEAR_LABELS[year] ?? null;
}
