@php
    $money = fn ($minor) => $minor === null ? null : $tenant->currency_code.' '.number_format($minor / 100, 2);
    $toneClass = ['pending' => 'neutral', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'neutral'];
@endphp

<x-layouts.admin title="Approvals">
    <style>
        .appr-card { border: 1px solid var(--line); border-radius: var(--radius-sm); background: #fff; padding: 16px 18px; }
        .appr-card + .appr-card { margin-top: 12px; }
        .appr-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
        .appr-type { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--brand-strong); }
        .appr-title { font-weight: 800; font-size: 15px; color: var(--ink); margin-top: 2px; }
        .appr-meta { color: var(--muted); font-size: 12.5px; margin-top: 4px; }
        .appr-amount { font-weight: 800; font-size: 16px; color: var(--ink); white-space: nowrap; }
        .appr-actions { display: flex; gap: 8px; margin-top: 14px; }
        .appr-actions form { margin: 0; }
        .appr-note { color: var(--ink-soft); font-size: 13px; margin-top: 8px; background: var(--panel-soft); border-radius: 8px; padding: 8px 12px; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Access control</div>
            <h1>Approvals</h1>
            <p class="subtle">Review requests that need your sign-off across {{ $tenant->name }}.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Waiting for you <span class="badge {{ $pending->isEmpty() ? 'neutral' : '' }}">{{ $pending->count() }}</span></h2>
                <p class="subtle">Only requests you are allowed to approve appear here.</p>
            </div>
        </div>
        <div class="panel-body">
            @if ($pending->isEmpty())
                <div class="empty">Nothing is waiting for your approval right now.</div>
            @else
                @foreach ($pending as $req)
                    <div class="appr-card">
                        <div class="appr-head">
                            <div>
                                <div class="appr-type">{{ $labels[$req->type] ?? $req->type }}</div>
                                <div class="appr-title">{{ $req->title }}</div>
                                <div class="appr-meta">
                                    Requested by {{ $req->requester?->name ?? 'Unknown' }}
                                    · {{ $req->branch?->name ?? 'All branches' }}
                                    · {{ $req->created_at?->diffForHumans() }}
                                </div>
                                @if ($req->description)
                                    <div class="appr-meta">{{ $req->description }}</div>
                                @endif
                                @if ($req->request_note)
                                    <div class="appr-note">“{{ $req->request_note }}”</div>
                                @endif
                            </div>
                            @if ($req->amount_minor !== null)
                                <div class="appr-amount">{{ $money($req->amount_minor) }}</div>
                            @endif
                        </div>
                        <div class="appr-actions">
                            <form method="POST" action="{{ route('admin.access.approvals.approve', $req) }}">
                                @csrf
                                <button class="btn primary btn-sm" type="submit">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.access.approvals.reject', $req) }}"
                                  onsubmit="return confirm('Reject this request?');">
                                @csrf
                                <button class="btn danger btn-sm" type="submit">Reject</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </section>

    <section class="panel" style="margin-top:20px;">
        <div class="panel-header">
            <div><h2 class="panel-title">Recently decided</h2></div>
        </div>
        <div class="panel-body">
            @if ($recent->isEmpty())
                <div class="empty">No decisions yet.</div>
            @else
                <div class="table-scroll">
                    <table class="table">
                        <thead><tr><th>Request</th><th>Type</th><th>Amount</th><th>Decision</th><th>By</th><th>When</th></tr></thead>
                        <tbody>
                            @foreach ($recent as $req)
                                <tr>
                                    <td><div class="cell-title">{{ $req->title }}</div><div class="cell-sub">{{ $req->requester?->name }}</div></td>
                                    <td>{{ $labels[$req->type] ?? $req->type }}</td>
                                    <td>{{ $money($req->amount_minor) ?? '—' }}</td>
                                    <td><span class="badge {{ $toneClass[$req->status->value] ?? 'neutral' }}">{{ $req->status->label() }}</span></td>
                                    <td>{{ $req->decider?->name ?? '—' }}</td>
                                    <td class="subtle">{{ $req->decided_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <style>.btn.btn-sm { padding: 7px 14px; font-size: 13px; }</style>
</x-layouts.admin>
