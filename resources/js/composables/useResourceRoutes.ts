import type { RouteDefinition } from '@/wayfinder';

export interface ResourceControllerActions {
    index: (options?: any) => RouteDefinition<any>;
    create: (options?: any) => RouteDefinition<any>;
    show: (args: any, options?: any) => RouteDefinition<any>;
    edit: (args: any, options?: any) => RouteDefinition<any>;
    destroy: (args: any, options?: any) => RouteDefinition<any>;
}

export interface ResourceRoutes {
    index: string;
    show: string;
    edit: string;
    create: string;
    destroy: string;
}

export function useResourceRoutes(
    resourceId: number | string | null,
    actions: ResourceControllerActions,
): ResourceRoutes {
    return {
        index: actions.index().url,
        show: resourceId ? actions.show(resourceId).url : actions.index().url,
        edit: resourceId ? actions.edit(resourceId).url : actions.index().url,
        create: actions.create().url,
        destroy: resourceId
            ? actions.destroy(resourceId).url
            : actions.index().url,
    };
}
