import {
    useLocation,
    useNavigate,
    useParams
} from '@/velzone/inertia-router';

function withRouter(Component : any) {
    function ComponentWithRouterProp(props : any) {
        const location = useLocation();
        const navigate = useNavigate();
        const params = useParams();

        return (
            <Component
                {...props}
                router={{ location, navigate, params }}
            />
        );
    }

    return ComponentWithRouterProp;
}

export default withRouter;