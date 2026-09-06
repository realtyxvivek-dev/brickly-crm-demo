# Calling Center Documentation

## Purpose
Calling Center is used to call Lead Bank numbers one by one through MCube. Admin or an authorized user creates a campaign, assigns it to a telecaller or agent, and the agent submits an outcome after every call.

The next call starts only after the current call outcome is submitted.

## Main Workflow
1. Open **Calling Center**.
2. Create a campaign from Lead Bank:
   - All Lead Bank
   - Unassigned leads
   - Tag folder
3. Select filters like city, source, status, and quantity.
4. Select the agent or telecaller.
5. Select next-call delay:
   - 2 minutes
   - 3 minutes
   - 5 minutes
   - Immediate after outcome
6. Start the campaign.
7. MCube sends the call to the selected agent.
8. Agent submits the call outcome.
9. The dashboard updates counters.
10. The next call starts after the selected delay.

## Agent Queue
The agent uses **My Calling Queue**.

The queue shows:
- Current campaign
- Current lead
- Phone number
- Call status
- Call start time
- Outcome form
- Upcoming calls

The agent must submit an outcome before the next call can start.

## Outcomes
Supported outcomes:
- Interested
- Not Interested
- Follow Up
- CNP
- Junk
- Meeting Request
- Site Visit Request

For **Follow Up**, date and time are required. The system creates a follow-up and a follow-up task.

## Campaign Rules
- One agent can have only one active call at a time.
- Duplicate leads are not added to the same campaign.
- Duplicate phone numbers are skipped.
- Dead, junk, and not-interested leads are skipped by default.
- MCube request and response are saved for every outbound attempt.
- Campaign calls are MCube-only. Browser `tel:` fallback is only for manual single-call buttons.

## Roles And Permissions
### Admin
Admin can:
- Create campaigns
- Start campaigns
- Pause campaigns
- Cancel campaigns
- View reports
- Export reports
- Access all Calling Center data

### Telecaller
Telecaller can:
- Open My Calling Queue
- Submit outcomes
- Pause own queue
- View own report
- View own recordings if enabled
- Use own attendance and profile

Telecaller cannot access by default:
- Lead Bank inventory
- Lead import/export
- User management
- CRM verification
- Closer/KYC
- Integrations
- Team reports
- Other users' calls or recordings

### Future Roles
CRM, manager, or another role can get Calling Center access by adding the required Calling Center permissions to that role.

## Permissions
Available permissions:
- `calling_center.view`
- `calling_center.create_campaign`
- `calling_center.agent_queue`
- `calling_center.submit_outcome`
- `calling_center.pause_own_queue`
- `calling_center.manual_push`
- `calling_center.approve_push`
- `calling_center.view_own_report`
- `calling_center.view_team_report`
- `calling_center.view_recordings`
- `calling_center.export_reports`

## Reports
Calling Center reports show:
- Total leads
- Pending calls
- Active calls
- Completed calls
- Failed calls
- Skipped calls
- Interested count
- Follow Up count
- CNP count
- Not Interested count
- Junk count
- Recording link when available

Campaign report can be exported as CSV.

## MCube Integration
Calling Center uses the existing MCube outbound service.

Ref ID format:

```text
calling_campaign:{campaign_id}:item:{item_id}
```

When MCube sends a webhook with this ref ID, the system links the call status and call log back to the campaign item.

## Background Processing
The queue processor runs every minute:

```bash
php artisan calling-center:process
```

It checks running campaigns and starts due calls when:
- campaign is running
- agent has no active call
- pending item is ready
- selected delay has passed

## Testing Checklist
1. Run migrations.
2. Configure MCube outbound settings.
3. Create or confirm a telecaller user has a phone number.
4. Create a campaign from Lead Bank.
5. Start the campaign.
6. Confirm MCube outbound attempt is created.
7. Open My Calling Queue as the telecaller.
8. Submit an outcome.
9. Confirm counters update.
10. Confirm next call starts after the selected delay.
11. Confirm skipped leads are not called.
12. Confirm export includes campaign call data.
