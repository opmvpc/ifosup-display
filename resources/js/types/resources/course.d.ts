interface Course {
    id: number;
    name: string;
    code: string;
    year?: number | null;
    teacher_id: number;
    teacher?: Teacher;
    groups?: Group[];
}
