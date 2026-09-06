<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation — CRM</title>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --text: #e2e8f0; --muted: #94a3b8; --accent: #38bdf8; --green: #34d399; --yellow: #fbbf24; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; margin: 0; padding: 1rem; }
        .wrap { max-width: 960px; margin: 0 auto; }
        h1 { font-size: 1.75rem; margin: 0 0 0.5rem; color: #fff; }
        h2 { font-size: 1.25rem; margin: 2rem 0 0.75rem; color: var(--accent); border-bottom: 1px solid var(--card); padding-bottom: 0.25rem; }
        h3 { font-size: 1.05rem; margin: 1.25rem 0 0.5rem; color: var(--muted); }
        p { margin: 0.5rem 0; color: var(--muted); }
        code, .path { font-family: 'Consolas', monospace; background: var(--card); padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.9em; }
        .path { color: var(--green); display: inline-block; margin: 0.25rem 0; }
        pre { background: var(--card); padding: 1rem; border-radius: 8px; overflow-x: auto; font-size: 0.85rem; margin: 0.5rem 0; }
        pre code { background: none; padding: 0; }
        table { width: 100%; border-collapse: collapse; margin: 0.75rem 0; font-size: 0.9rem; }
        th, td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--card); }
        th { color: var(--muted); font-weight: 600; }
        .method { font-weight: 600; }
        .method.get { color: var(--green); }
        .method.post { color: var(--yellow); }
        .method.put { color: #60a5fa; }
        .method.delete { color: #f87171; }
        .badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; }
        .badge.public { background: #065f46; color: #6ee7b7; }
        .badge.auth { background: #1e3a8a; color: #93c5fd; }
        .badge.role { background: #4c1d95; color: #c4b5fd; }
        ul { margin: 0.25rem 0; padding-left: 1.25rem; color: var(--muted); }
        .note { background: rgba(56,189,248,0.1); border-left: 3px solid var(--accent); padding: 0.5rem 0.75rem; margin: 0.75rem 0; border-radius: 0 4px 4px 0; font-size: 0.9rem; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .toc { margin: 1rem 0; }
        .toc a { display: block; padding: 0.25rem 0; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>API Documentation</h1>
    <p>CRM REST API — Base URL: <code>{{ $baseUrl }}</code></p>
    <p>This page is only accessible via this unique URL. Do not share the link publicly if you want to keep docs private.</p>

    <h2 id="overview">Overview</h2>
    <ul>
        <li><strong>Base URL:</strong> <code>{{ $baseUrl }}</code></li>
        <li><strong>Content-Type:</strong> <code>application/json</code></li>
        <li><strong>Accept:</strong> <code>application/json</code></li>
        <li><strong>Authentication:</strong> Bearer token (Sanctum). Send header: <code>Authorization: Bearer &lt;token&gt;</code></li>
    </ul>
    <h3>Error responses</h3>
    <p>4xx/5xx return JSON e.g. <code>{"message": "Unauthenticated."}</code> or validation <code>{"message": "...", "errors": {"field": ["..."]}}</code></p>
    <h3>Pagination</h3>
    <p>List endpoints often return <code>data</code>, <code>meta</code> (current_page, last_page, per_page, total), <code>links</code>.</p>

    <h2 id="auth">Authentication</h2>
    <h3>Login (get token)</h3>
    <table>
        <tr><th>Method</th><th>Path</th><th>Auth</th><th>Body</th></tr>
        <tr>
            <td class="method post">POST</td>
            <td><code>/api/login</code></td>
            <td><span class="badge public">Public</span></td>
            <td><code>email</code>, <code>password</code></td>
        </tr>
    </table>
    <p>Response:</p>
    <pre><code>{
  "user": { "id", "name", "email", "role": { "id", "name", "slug" }, ... },
  "token": "1|..."
}</code></pre>
    <p>Use <code>token</code> in <code>Authorization: Bearer &lt;token&gt;</code> for all protected endpoints.</p>

    <h3>Logout</h3>
    <table>
        <tr><th>Method</th><th>Path</th><th>Auth</th></tr>
        <tr><td class="method post">POST</td><td><code>/api/logout</code></td><td><span class="badge auth">Bearer</span></td></tr>
    </table>

    <h3>Current user</h3>
    <table>
        <tr><th>Method</th><th>Path</th><th>Auth</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/me</code></td><td><span class="badge auth">Bearer</span></td></tr>
    </table>
    <p>Returns current user with <code>role</code> and <code>manager</code>.</p>

    <h2 id="roles">Roles (slugs)</h2>
    <table>
        <tr><th>Slug</th><th>Usage</th></tr>
        <tr><td><code>admin</code></td><td>Full access</td></tr>
        <tr><td><code>crm</code></td><td>CRM operations, leads, users, targets</td></tr>
        <tr><td><code>sales_head</code></td><td>Sales head</td></tr>
        <tr><td><code>sales_manager</code></td><td>Team, meetings, site visits, prospects</td></tr>
        <tr><td><code>sales_executive</code></td><td>Telecaller / sales executive (same API prefix)</td></tr>
        <tr><td><code>finance_manager</code></td><td>Incentive verification</td></tr>
    </table>

    <h2 id="leads">Leads (auth:sanctum)</h2>
    <table>
        <tr><th>Method</th><th>Path</th><th>Notes</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/leads</code></td><td>List (paginated). Query: per_page, page, status, source, assigned_to, etc.</td></tr>
        <tr><td class="method post">POST</td><td><code>/api/leads</code></td><td>Create lead</td></tr>
        <tr><td class="method get">GET</td><td><code>/api/leads/{id}</code></td><td>Show one</td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/leads/{id}</code></td><td>Update</td></tr>
        <tr><td class="method delete">DELETE</td><td><code>/api/leads/{id}</code></td><td>Soft delete</td></tr>
        <tr><td class="method post">POST</td><td><code>/api/leads/bulk-assign</code></td><td>Body: lead_ids[], assigned_to</td></tr>
        <tr><td class="method post">POST</td><td><code>/api/leads/transfer-all-from-user</code></td><td>Transfer all leads from one user</td></tr>
        <tr><td class="method post">POST</td><td><code>/api/leads/{lead}/assign</code></td><td>Body: assigned_to</td></tr>
    </table>

    <h2 id="lead-model">Lead model (main fields)</h2>
    <p><code>name</code>, <code>email</code>, <code>phone</code>, <code>address</code>, <code>city</code>, <code>state</code>, <code>pincode</code>, <code>source</code>, <code>status</code>, <code>property_type</code>, <code>budget_min</code>, <code>budget_max</code>, <code>budget</code>, <code>requirements</code>, <code>notes</code>, <code>created_by</code>, <code>last_contacted_at</code>, <code>next_followup_at</code>, <code>preferred_location</code>, <code>preferred_size</code>, <code>preferred_projects</code>, <code>use_end_use</code>, <code>possession_status</code>, <code>cnp_count</code>, <code>is_blocked</code>, <code>blocked_reason</code>, <code>is_dead</code>, <code>dead_reason</code>, <code>dead_at_stage</code>, <code>marked_dead_at</code>, <code>marked_dead_by</code>.</p>
    <h3>Lead status (allowed values)</h3>
    <p><code>new</code>, <code>connected</code>, <code>verified_prospect</code>, <code>meeting_scheduled</code>, <code>meeting_completed</code>, <code>visit_scheduled</code>, <code>visit_done</code>, <code>revisited_scheduled</code>, <code>revisited_completed</code>, <code>closed</code>, <code>dead</code>, <code>junk</code>, <code>not_interested</code>, <code>on_hold</code>.</p>

    <h2 id="public">Public / Webhooks</h2>
    <table>
        <tr><th>Method</th><th>Path</th><th>Auth</th></tr>
        <tr><td class="method post">POST</td><td><code>/api/pabbly/webhook</code></td><td><span class="badge public">Public</span></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/google-sheets/leads</code></td><td><span class="badge public">Public</span></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/login</code></td><td><span class="badge public">Public</span> (telecaller login)</td></tr>
    </table>

    <h2 id="dashboard">Dashboard &amp; targets</h2>
    <table>
        <tr><th>Method</th><th>Path</th><th>Auth</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/dashboard</code></td><td>Bearer</td></tr>
        <tr><td class="method get">GET</td><td><code>/api/targets/my-targets</code></td><td>Bearer</td></tr>
        <tr><td class="method get">GET</td><td><code>/api/targets/team-progress</code></td><td>Bearer (sales_manager)</td></tr>
        <tr><td class="method get">GET</td><td><code>/api/targets/overview</code></td><td>Bearer (admin, crm)</td></tr>
    </table>

    <h2 id="site-visits">Site visits &amp; follow-ups</h2>
    <table>
        <tr><th>Method</th><th>Path</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/site-visits</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/site-visits</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/site-visits/{id}</code></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/site-visits/{id}</code></td></tr>
        <tr><td class="method delete">DELETE</td><td><code>/api/site-visits/{id}</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/follow-ups</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/follow-ups</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/follow-ups/{id}</code></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/follow-ups/{id}</code></td></tr>
        <tr><td class="method delete">DELETE</td><td><code>/api/follow-ups/{id}</code></td></tr>
    </table>

    <h2 id="notifications">Notifications</h2>
    <table>
        <tr><th>Method</th><th>Path</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/notifications</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/notifications/unread</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/notifications/{id}/read</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/notifications/{id}/click</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/notifications/mark-all-read</code></td></tr>
    </table>

    <h2 id="users">Users (admin permission)</h2>
    <table>
        <tr><th>Method</th><th>Path</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/users</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/users</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/users/{id}</code></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/users/{id}</code></td></tr>
        <tr><td class="method delete">DELETE</td><td><code>/api/users/{id}</code></td></tr>
    </table>

    <h2 id="telecaller">Telecaller / Sales Executive (<code>role:sales_executive</code>)</h2>
    <p>Prefix: <code>/api/telecaller</code>. Auth: Bearer (after telecaller login or main login with sales_executive role).</p>
    <table>
        <tr><th>Method</th><th>Path</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/whoami</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/logout</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/stats</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/dashboard</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/dashboard/stats</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/dashboard/urgent-tasks</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/dashboard/schedule</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/dashboard/performance</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/leads</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/calling-queue</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/completed-calls</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/follow-up-calls</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/cnp-calls</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/prospects</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/tasks</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/tasks/stats</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/tasks/schedule-call</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/tasks/{task}/initiate-call</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/tasks/{task}/call-outcome</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/tasks/{taskId}/outcome</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/tasks/{task}/lead-form</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/tasks/{taskId}/submit-for-verification</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/update-call-status</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/mark-cnp</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/mark-broker</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/schedule-follow-up</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/create-prospect</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/prospects/create</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/recall-assignment</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/blacklist-number</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/users</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/profile</code></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/telecaller/profile</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/profile/picture</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/profile/password</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/profile/availability</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/broadcast/unread</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/broadcast/{id}/read</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/site-visits/eligible-for-incentive</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/telecaller/site-visits/{id}/request-incentive</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/incentives</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecaller/incentives/{id}</code></td></tr>
    </table>

    <h2 id="call-logs">Call logs</h2>
    <table>
        <tr><th>Method</th><th>Path</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/call-logs</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/call-logs</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/call-logs/bulk-sync</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/call-logs/statistics</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/call-logs/team-statistics</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/call-logs/dashboard-stats</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/call-logs/{id}</code></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/call-logs/{id}</code></td></tr>
    </table>

    <h2 id="sales-manager">Sales Manager (<code>admin, crm, sales_head, sales_manager</code>)</h2>
    <p>Prefix: <code>/api/sales-manager</code>.</p>
    <table>
        <tr><th>Method</th><th>Path</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/profile</code></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/sales-manager/profile</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/profile/picture</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/profile/password</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/team/member/{id}</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/team/performance</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/achievements</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/prospects</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/prospects</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/prospects/pending</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/prospects/{id}/verify</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/prospects/{id}/reject</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/tasks</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/tasks/{id}</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/tasks/schedule-call</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/tasks/{id}/update-lead</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/tasks/{id}/verify</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/tasks/{id}/reject</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/tasks/{id}/cnp</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/tasks/{id}/complete</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/meetings</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/meetings</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/meetings/{id}</code></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/sales-manager/meetings/{id}</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/meetings/{id}/complete</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/meetings/{id}/cancel</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/meetings/{id}/reschedule</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/meetings/{id}/convert-to-site-visit</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/meetings/{id}/verify</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/meetings/{id}/reject</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/site-visits</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/site-visits</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/site-visits/{id}/complete</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/site-visits/{id}/reschedule</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/sales-manager/site-visits/{id}/request-incentive</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/incentives</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/sales-manager/incentives/{id}</code></td></tr>
    </table>

    <h2 id="crm">CRM (<code>role:crm</code>)</h2>
    <p>Prefix: <code>/api/crm</code>. Dashboard stats under <code>crm_dashboard_access</code> (all except CRM Admin/Sale Head).</p>
    <table>
        <tr><th>Method</th><th>Path</th></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/login</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/whoami</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/logout</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/dashboard/stats</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/dashboard/filter-roles</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/dashboard/telecaller-stats</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/dashboard/leads-pending-response</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/dashboard/average-response-time</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/dashboard/daily-prospects</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/add-lead</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/imported-leads</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/assign-leads</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/users</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/roles</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/users</code></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/crm/users/{id}</code></td></tr>
        <tr><td class="method delete">DELETE</td><td><code>/api/crm/users/{id}</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/transfer-leads</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/blacklist</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/blacklist</code></td></tr>
        <tr><td class="method delete">DELETE</td><td><code>/api/crm/blacklist/{id}</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/targets</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/targets</code></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/crm/targets/{id}</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/crm/pending-verifications</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/verify-prospect/{id}</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/reject-prospect/{id}</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/meetings/{id}/verify</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/meetings/{id}/reject</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/site-visits/{id}/verify</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/site-visits/{id}/reject</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/site-visits/{id}/verify-closer</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/site-visits/{id}/reject-closer</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/site-visits/{id}/verify-closing</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/crm/site-visits/{id}/reject-closing</code></td></tr>
    </table>

    <h2 id="admin">Admin (admin, crm)</h2>
    <table>
        <tr><th>Method</th><th>Path</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/admin/dead-leads</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/admin/dead-meetings</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/admin/dead-site-visits</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/admin/meetings/{id}</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/admin/site-visits/{id}</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/admin/prospects/{id}</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/admin/verifications/pending</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/admin/verifications/pending-closers</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/admin/verifications/verified</code></td></tr>
    </table>

    <h2 id="builders-projects">Builders &amp; projects</h2>
    <table>
        <tr><th>Method</th><th>Path</th><th>Notes</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/builders</code></td><td>List</td></tr>
        <tr><td class="method post">POST</td><td><code>/api/builders</code></td><td>admin, crm</td></tr>
        <tr><td class="method get">GET</td><td><code>/api/builders/{id}</code></td><td></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/builders/{id}</code></td><td>admin, crm</td></tr>
        <tr><td class="method delete">DELETE</td><td><code>/api/builders/{id}</code></td><td>admin, crm</td></tr>
        <tr><td class="method post">POST</td><td><code>/api/builders/{id}/logo</code></td><td>admin, crm</td></tr>
        <tr><td class="method get">GET</td><td><code>/api/builders/{id}/projects</code></td><td></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/builders/{id}/projects</code></td><td>admin, crm</td></tr>
        <tr><td class="method get">GET</td><td><code>/api/projects</code></td><td></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/projects/{id}</code></td><td></td></tr>
        <tr><td class="method put">PUT</td><td><code>/api/projects/{id}</code></td><td>admin, crm</td></tr>
        <tr><td class="method delete">DELETE</td><td><code>/api/projects/{id}</code></td><td>admin, crm</td></tr>
        <tr><td class="method get">GET</td><td><code>/api/projects/{id}/detail</code></td><td></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/projects/{id}/collaterals</code></td><td></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/projects/{id}/pricing</code></td><td></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/projects/{id}/unit-types</code></td><td></td></tr>
    </table>

    <h2 id="forms">Dynamic forms</h2>
    <table>
        <tr><th>Method</th><th>Path</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/forms/{identifier}</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/forms/{identifier}/render</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/forms/{identifier}/submit</code></td></tr>
    </table>

    <h2 id="misc">Other</h2>
    <table>
        <tr><th>Method</th><th>Path</th></tr>
        <tr><td class="method get">GET</td><td><code>/api/interested-project-names</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/telecallers</code></td></tr>
        <tr><td class="method get">GET</td><td><code>/api/finance-manager/incentives</code></td><td>role: finance_manager</td></tr>
        <tr><td class="method get">GET</td><td><code>/api/finance-manager/incentives/{id}</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/finance-manager/incentives/{id}/verify</code></td></tr>
        <tr><td class="method post">POST</td><td><code>/api/finance-manager/incentives/{id}/reject</code></td></tr>
    </table>

    <div class="note">
        <strong>Unique URL:</strong> This documentation is only available at the URL you used to open it. There is no link to it from the main application. To share with your Flutter developer, send them only this page URL. To change the access key, set <code>DEVELOPER_DOCS_ACCESS_KEY</code> in your <code>.env</code> file.
    </div>
</div>
</body>
</html>
