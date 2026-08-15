import {
    useLocation,
    useNavigate,
    useParams
} from '@/velzone/inertia-router';

function withRouter(Component : any) {
    function ComponentWithRouterProp(props : any) {
        let location = useLocation();
        let navigate = useNavigate();
        let params = useParams();
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