declare module 'lodash' {
    export function get<T = unknown>(
        object: unknown,
        path: string | string[],
        defaultValue?: T,
    ): T;

    export function isEmpty(value: unknown): boolean;
}
