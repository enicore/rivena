// The event log page.
class EventController extends Controller {
    init() {
        super.init();
        this.table = new Table(`${userPath}/events/list`, this.data.table, {query: true});
    }
}

window.eventController = () => new EventController();
