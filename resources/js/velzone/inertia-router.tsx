import { Link as InertiaLink, router, usePage } from '@inertiajs/react';
import { forwardRef, useCallback, useEffect } from 'react';
import type { ComponentProps, ReactNode } from 'react';

type Destination =
    | string
    | {
          pathname?: string;
          search?: string;
          hash?: string;
      };

type LinkProps = Omit<ComponentProps<typeof InertiaLink>, 'href'> & {
    to?: Destination;
};

const destinationToHref = (destination: Destination = '#'): string => {
    if (typeof destination === 'string') {
        return destination === '/#' ? '#' : destination;
    }

    return (
        `${destination.pathname ?? ''}${destination.search ?? ''}${destination.hash ?? ''}` ||
        '#'
    );
};

export const Link = forwardRef<HTMLAnchorElement, LinkProps>(
    ({ to = '#', ...props }, ref) => (
        <InertiaLink ref={ref} href={destinationToHref(to)} {...props} />
    ),
);

Link.displayName = 'VelzoneInertiaLink';

type NavLinkProps = Omit<LinkProps, 'className' | 'children'> & {
    className?: string | ((state: { isActive: boolean }) => string);
    children?: ReactNode | ((state: { isActive: boolean }) => ReactNode);
};

export const NavLink = forwardRef<HTMLAnchorElement, NavLinkProps>(
    ({ className, children, to = '#', ...props }, ref) => {
        const location = useLocation();
        const href = destinationToHref(to);
        const isActive =
            href !== '#' && location.pathname === href.split(/[?#]/)[0];
        const state = { isActive };

        return (
            <Link
                ref={ref}
                to={to}
                className={
                    typeof className === 'function'
                        ? className(state)
                        : className
                }
                {...props}
            >
                {typeof children === 'function' ? children(state) : children}
            </Link>
        );
    },
);

NavLink.displayName = 'VelzoneInertiaNavLink';

export const useLocation = () => {
    const { url } = usePage();
    const [pathAndSearch, hash = ''] = url.split('#', 2);
    const [pathname, search = ''] = pathAndSearch.split('?', 2);

    return {
        pathname: pathname || '/',
        search: search ? `?${search}` : '',
        hash: hash ? `#${hash}` : '',
        state: null,
        key: url,
    };
};

export const useNavigate = () =>
    useCallback(
        (
            destination: Destination | number,
            options: Record<string, unknown> = {},
        ) => {
            if (typeof destination === 'number') {
                window.history.go(destination);

                return;
            }

            router.visit(destinationToHref(destination), options);
        },
        [],
    );

export const useParams = <T extends Record<string, string | undefined>>() =>
    ({}) as T;

export const Navigate = ({
    to,
    replace = false,
}: {
    to: Destination;
    replace?: boolean;
}) => {
    useEffect(() => {
        router.visit(destinationToHref(to), { replace });
    }, [replace, to]);

    return null;
};
