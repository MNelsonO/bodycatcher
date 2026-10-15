export type RememberedUser = {
    email: string;
    name: string;
};

export const REMEMBERED_USER_KEY = 'kemet.desk.last_user';

export function readRememberedUser(): RememberedUser | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = localStorage.getItem(REMEMBERED_USER_KEY);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as RememberedUser;
        if (!parsed?.email) {
            return null;
        }

        return {
            email: parsed.email,
            name: parsed.name || '',
        };
    } catch {
        return null;
    }
}

export function writeRememberedUser(user: RememberedUser): void {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(
        REMEMBERED_USER_KEY,
        JSON.stringify({
            email: user.email,
            name: user.name || '',
        }),
    );
}

export function clearRememberedUser(): void {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.removeItem(REMEMBERED_USER_KEY);
}

export function initialsFor(name: string, email: string): string {
    const source = name.trim() || email.trim();
    const parts = source.split(/[\s@._-]+/).filter(Boolean);

    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }

    return source.slice(0, 2).toUpperCase() || 'KT';
}

export function firstNameFor(user: RememberedUser): string {
    if (user.name.trim()) {
        return user.name.trim().split(/\s+/)[0] ?? user.name;
    }

    return user.email.split('@')[0] ?? user.email;
}
