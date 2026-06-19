<dialog class="dialog" id="group-dialog">
    <div class="dialog-header"><div><h2 class="panel-title">Add customer group</h2><p class="subtle">Create a segment for targeted communication and reporting.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.customers.groups.store') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Name</label><input name="name" required></div>
                <div class="field"><label>Code</label><input name="code"></div>
                <div class="field full"><label>Description</label><textarea name="description"></textarea></div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Add group</button></div>
        </form>
    </div>
</dialog>
