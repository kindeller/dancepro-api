<dialog id="event-role-dialog" class="event-workflow-dialog">
    <header><h2>Add role</h2><button type="button" class="secondary dialog-close" aria-label="Close add role">×</button></header>
    <p class="dialog-event-name muted"></p>
    <p>This role applies to every shift for this event. Choose the crew member after adding it.</p>
    <div class="role-options"></div>
    <p class="dialog-error" role="alert" hidden></p>
</dialog>
<dialog id="event-status-dialog" class="event-workflow-dialog">
    <header><h2>Event status</h2><button type="button" class="secondary dialog-close" aria-label="Close event status">×</button></header>
    <p class="dialog-event-name muted"></p>
    <form id="event-status-form">
        <label>Action<select name="action" required>
            <option value="">Choose action</option>
            <option value="draft">Draft — keep availability closed</option>
            <option value="open">Request availability</option>
            <option value="closed">Close availability — start rostering</option>
            <option value="publish_roster">Publish roster and notify crew</option>
        </select></label>
        <label class="status-deadline" hidden>Response deadline (5:00 pm)<input type="date" name="deadline" min="{{ today()->toDateString() }}"></label>
        <p class="status-action-help muted"></p>
        <p class="muted">Ready is automatic once every required role is assigned, published and acknowledged by crew.</p>
        <p class="dialog-error" role="alert" hidden></p>
        <footer><button type="button" class="secondary dialog-close">Cancel</button><button type="submit">Apply</button></footer>
    </form>
</dialog>
