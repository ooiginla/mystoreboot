@php
    $sevTone = ['high' => 'danger', 'medium' => 'warning', 'low' => 'neutral'];
    $sevLabel = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
    $activeMemberships = $memberships->filter(fn ($m) => $m->status->value === 'active');
@endphp

<x-layouts.admin title="Access review">
    <style>
        .review-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 20px; }
        @media (max-width: 800px) { .review-stats { grid-template-columns: repeat(2, 1fr); } }
        .stat .stat-label { color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .stat .stat-value { font-size: 26px; font-weight: 800; color: var(--ink); margin-top: 6px; letter-spacing: -.02em; }
        .finding { border: 1px solid var(--line); border-left-width: 4px; border-radius: var(--radius-sm); padding: 13px 16px; background: #fff; }
        .finding + .finding { margin-top: 10px; }
        .finding.sev-high { border-left-color: var(--danger); }
        .finding.sev-medium { border-left-color: #f59e0b; }
        .finding.sev-low { border-left-color: var(--muted); }
        .finding-title { font-weight: 800; font-size: 14px; color: var(--ink); display: flex; align-items: center; gap: 8px; }
        .finding-detail { color: var(--ink-soft); font-size: 13px; margin-top: 4px; line-height: 1.5; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.danger { background: var(--danger-bg); color: var(--danger-strong); }
        .perm-meter { display: inline-flex; align-items: center; gap: 8px; }
        .perm-bar { width: 90px; height: 7px; border-radius: 999px; background: var(--panel-soft); overflow: hidden; }
        .perm-bar span { display: block; height: 100%; background: var(--brand); border-radius: 999px; }
        .audit-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line-soft); }
        .audit-item:last-child { border-bottom: 0; }
        .audit-dot { width: 8px; height: 8px; border-radius: 999px; margin-top: 6px; flex: 0 0 auto; background: var(--brand); }
        .audit-dot.security { background: var(--danger); }
        .audit-dot.approvals { background: #f59e0b; }
        .audit-body { min-width: 0; }
        .audit-desc { color: var(--ink); font-size: 13.5px; font-weight: 600; }
        .audit-meta { color: var(--muted); font-size: 12px; margin-top: 2px; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Access control</div>
            <h1>Access review</h1>
            <p class="subtle">Who can do what across {{ $tenant->name }}, and where the risks are.</p>
        </div>
        <a class="btn secondary" href="{{ route('admin.business.index', ['tenant' => $tenant->id]) }}#roles">Manage roles</a>
    </div>

    <div class="review-stats">
        <div class="stat"><div class="stat-label">Roles</div><div class="stat-value">{{ $roles->count() }}</div></div>
        <div class="stat"><div class="stat-label">Members</div><div class="stat-value">{{ $activeMemberships->count() }}</div></div>
        <div class="stat"><div class="stat-label">Risk findings</div><div class="stat-value">{{ count($findings) }}</div></div>
        <div class="stat"><div class="stat-label">Permissions</div><div class="stat-value">{{ $totalPermissions }}</div></div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Risk findings</h2>
                <p class="subtle">Separation-of-duties, privilege concentration and gaps we noticed.</p>
            </div>
        </div>
        <div class="panel-body">
            @if (empty($findings))
                <div class="empty">No risky access patterns detected. 👍</div>
            @else
                @foreach ($findings as $f)
                    <div class="finding sev-{{ $f['severity'] }}">
                        <div class="finding-title">
                            <span class="badge {{ $sevTone[$f['severity']] }}">{{ $sevLabel[$f['severity']] }}</span>
                            {{ $f['title'] }}
                            <span class="subtle" style="font-weight:600;">· {{ $f['subject'] }}</span>
                        </div>
                        <div class="finding-detail">{{ $f['detail'] }}</div>
                    </div>
                @endforeach
            @endif
        </div>
    </section>

    <section class="panel" style="margin-top:20px;">
        <div class="panel-header"><div><h2 class="panel-title">Role usage</h2><p class="subtle">How broad each role is and how many people hold it.</p></div></div>
        <div class="panel-body">
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th>Role</th><th>Type</th><th>Users</th><th>Breadth</th></tr></thead>
                    <tbody>
                        @foreach ($roles as $role)
                            @php $count = $role->permissions->count(); $pct = $totalPermissions ? round($count / $totalPermissions * 100) : 0; @endphp
                            <tr>
                                <td><div class="cell-title">{{ $role->name }}</div></td>
                                <td>
                                    @if ($role->is_protected)<span class="badge">Protected</span>
                                    @elseif ($role->is_system)<span class="badge neutral">System</span>
                                    @else<span class="badge neutral">Custom</span>@endif
                                </td>
                                <td>{{ $role->memberships_count }}</td>
                                <td>
                                    <span class="perm-meter">
                                        <span class="perm-bar"><span style="width: {{ $pct }}%"></span></span>
                                        <span class="subtle">{{ $count }}/{{ $totalPermissions }}</span>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:20px;">
        <div class="panel-header"><div><h2 class="panel-title">Who has access</h2><p class="subtle">Every member and their effective role and branch scope.</p></div></div>
        <div class="panel-body">
            @if ($activeMemberships->isEmpty())
                <div class="empty">No active members.</div>
            @else
                <div class="table-scroll">
                    <table class="table">
                        <thead><tr><th>User</th><th>Role</th><th>Branch scope</th><th>Permissions</th></tr></thead>
                        <tbody>
                            @foreach ($activeMemberships as $m)
                                <tr>
                                    <td><div class="cell-title">{{ $m->user?->name }}</div><div class="cell-sub">{{ $m->user?->email }}</div></td>
                                    <td>{{ $m->role?->name ?? '— none —' }}</td>
                                    <td>{{ $m->branch?->name ?? 'All branches' }}</td>
                                    <td class="subtle">{{ $m->role ? $m->role->permissions->count().' permissions' : 'No access' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <section class="panel" style="margin-top:20px;">
        <div class="panel-header"><div><h2 class="panel-title">Recent security activity</h2><p class="subtle">Role changes, access assignments, approvals and blocked attempts.</p></div></div>
        <div class="panel-body">
            @if ($auditLogs->isEmpty())
                <div class="empty">No security events recorded yet.</div>
            @else
                @foreach ($auditLogs as $log)
                    <div class="audit-item">
                        <span class="audit-dot {{ $log->category }}"></span>
                        <div class="audit-body">
                            <div class="audit-desc">{{ $log->description }}</div>
                            <div class="audit-meta">{{ $log->actor_name ?? 'System' }} · {{ $log->created_at?->diffForHumans() }} · {{ $log->action }}</div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </section>
</x-layouts.admin>
