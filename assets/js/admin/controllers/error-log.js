// The Error Log page.
class ErrorLogController extends Controller {
    init() {
        super.init();
        this.table = new Table(`${userPath}/error-log/list`, this.data.table, {query: true});
    }
}

window.errorLogController = () => new ErrorLogController();
