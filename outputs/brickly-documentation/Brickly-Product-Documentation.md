# Brickly CRM Product Documentation

Product overview and evaluator guide

Version 1.0 | 6 September 2026

Founder: Vivek Kumar Verma

Website: https://bihtech.in

Product access: https://crm.bihtech.in — authentication required

Contact: +91 93692 05635

## Purpose and product overview

Brickly CRM is a real estate operations product for channel partners, brokerages, developers and sales teams. It brings lead ownership, follow-ups, site visits and reporting into a shared workflow. Its broader product direction connects marketing, employee operations, finance and post-sales work to the same business context.

This guide helps evaluators understand the product, follow its core customer journey and distinguish current capabilities from the planned demo and AI roadmap. It also explains how to interpret visit reports without confusing a completed visit with a lead's current sales status.

The product direction draws on the founder's four years of hands-on real estate experience and feedback about everyday CRM use. The intended benefit is clearer ownership and operational visibility. No conversion improvement, revenue uplift or response-time guarantee is asserted here.

## Reading the capability status

- Existing core: Lead CRM, visit records and reports are represented in the current product evidence. Availability depends on role and configuration.
- Implementation present: Local application code contains the module or integration workflow. This does not certify deployment, provider connectivity or successful end-to-end operation in a reviewer demo.
- Planned demo: The proposed isolated environment and sample-data setup have not been confirmed as deployed.
- Roadmap: Future capability; not presented as functioning AI or as a committed delivery date.

The module catalogue describes scope, not a release certificate. Features should be demonstrated in the target environment before being advertised as live there.

## Intended users

Founders review performance; CRM managers coordinate ownership and activities; salespeople and telecallers manage customer interactions. Marketing teams inspect lead sources. HR and finance teams use their configured employee, payment and approval modules.

Access to records and actions depends on the configured role and permissions.

---

## Core sales workflow

### Capture and identify a lead

A lead enters through a configured source, form, integration or manual entry. The record provides customer details, source and ownership context. Before creating a second record, search by phone and review the existing customer history. Keep international country codes intact.

Lead Bank is a separate operational concept for imported or pooled contacts. A bulk contact list should not automatically be treated as an active enquiry. Report scope should explicitly identify whether it covers operational leads, bank contacts or both.

### Assign responsibility

An authorised operator assigns or transfers responsibility, or a configured assignment workflow chooses a user. The implementation contains specific-user and percentage rules, and round-robin and first-available paths in assignment services. These paths occur in different configurations; they should not be assumed to appear together in every screen.

Capacity-based or performance-adaptive assignment remains a validation item. Assignment alone does not prove that a salesperson has contacted the customer.

### Record contact and the next action

The salesperson records the interaction, outcome and next follow-up. Examples include follow-up, not interested and CNP, used here to mean call not picked. Use the application's configured labels when recording an outcome.

Current lead status describes the present customer state. A call outcome describes a particular interaction. A lead can have an earlier completed visit and later become not interested or remain in follow-up.

### Schedule and complete a visit

Create a site visit against the relevant lead, record the scheduled date, responsible user and project or location, then record completion. Review the customer timeline and any configured verification workflow. Scheduled time and completion time are distinct fields and should remain distinct in reports.

Repeat visits should remain separate activity records. Do not delete a second visit merely because the phone number matches another row. Use lead and visit identifiers to investigate suspected duplicates.

### Continue after the visit

Update the customer outcome and next action after the visit. Where booking and post-sales modules are enabled, the next workflow covers selected property details, booking records, documents and payment follow-through. Real-time inventory locking and an automatic complete handoff are not claimed as verified features.

### Review the full journey

A manager should be able to compare ownership, activity dates, completed visits, the present lead status and the next follow-up. This is the core walkthrough recommended for a reviewer before exploring additional modules.

---

## Module catalogue

### Lead CRM and reporting

Existing core. Lead records, ownership, customer timelines, follow-ups, site visits and reporting form the main evaluation journey. The application also contains Insight Sheet, Lead Bank, assignment, quality review and task workflows. Their exact access and actions require configuration-specific validation.

### Marketing and communication

Implementation present. Meta operations, website lead intake, 99acres, Google Sheets, IVR, WhatsApp and Instagram integration components are present in the project. Instagram includes rule and flow management and conversation takeover paths. A connector's presence does not establish that the external account is connected or permitted to send messages.

Evaluate each connector with its own configuration and test record. Housing, Magicbricks and any other unsupported source must remain planned or subject to API validation. RCS is roadmap. Automatic conversation summaries are also roadmap AI, not a generic consequence of connecting WhatsApp or IVR.

### HRMS and employee operations

Implementation present. The project contains attendance, leave, regularization, overtime, employee details, review workflows and payroll components. Payroll includes preparation, adjustments, employee preview or release, corrections and submission to admin.

For a demo, show fictional employee records and an example approval journey. Attendance policies, calculation rules and permissions must be configured and verified before operational use. No absolute fraud prevention or tamper-proof location claim is made.

### Finance and purchase orders

Implementation present. Finance components include expenses, payroll-related views, purchase orders, payment proof and receipt workflows. The purchase-order controller includes creation, editing, payment recording, receipt and PDF download paths.

Demonstrate a fictional request through its enabled approval and payment stages. Verify calculation and approval behaviour in the target environment. This documentation does not claim statutory accounting or tax compliance certification.

### Post-sales and builder operations

Implementation present. Post-sales code includes cases, demands, transactions, payment verification, documents, builder claims, receipts, release schemes and invoice issue or download actions.

A reviewer should inspect one sample case and trace the relationship between its demand, payment entry and verification. A recorded transaction and a verified payment are different states. Avoid interpreting a pending entry as money received.

### Internal execution and support

Implementation present. Execution Desk, tasks, checklists, attachments, saved views, knowledge base and support-ticket components support internal work. These modules should be demonstrated only where enabled for the demo role.

---

## Understanding reports and exports

### Choose the question before the report

To find customers who have visited and are now in follow-up, start with completed visit history, then inspect the current lead status. Filtering only on the current status Visit Done can miss customers whose status changed after visiting.

To answer who conducted a visit, distinguish the visit's assigned user from the lead's current owner and the person who entered or completed the activity. These can differ after reassignment. Check the report's ownership definition before attributing a team's historical work.

### Field meanings

- Visit Date: The stored scheduled visit time.
- Completed Date: The stored completion time; not necessarily the scheduled time.
- Visit Sequence: Fresh Visit, 2nd Visit, 3rd Visit or Not Specified in the inspected formatter.
- Entry Stage: The stored lead type or stage associated with the visit record. A value such as Meeting does not by itself change a completed site visit into a meeting.
- Status: The visit record's state, such as completed.
- Current Lead Status: The customer's current resolved CRM status.
- Latest Call Outcome: The latest recorded call result; it may differ from current lead status.
- Next Follow-up: The next recorded follow-up value, when available.
- Assigned To: The visit's assigned user in the inspected report formatter.
- Queue State: Visibility in an operational queue; visibility is not proof that a historical visit never occurred.

### Repeat rows and missing records

A visit report is an activity report. One customer may appear multiple times because they visited more than once. A unique-customer report should count distinct lead identifiers separately. Phone-only deduplication can incorrectly combine different records or discard valid repeat visits.

When a customer appears missing, check date basis and range, visit status, selected user, exclusions, role scope and any spreadsheet filters. Search the full phone value, including the country code. Compare the lead timeline with the underlying visit records before concluding that data was deleted.

### Download scope and limits

In the inspected local report controller, CSV requests select the full matching query without an explicit row cap. PDF requests are capped at 10,000 records. Most report previews select 200 rows; the site-visit preview uses the full query. Deployment behaviour may differ and should be checked before submission.

The local CSV path still loads the selected records into memory. Large exports can therefore encounter memory limits even without a row cap. For large-volume use, streamed or batched exports require validation; this guide does not claim that the earlier export memory issue is fixed.

When opening CSV in Excel, import phone columns as text to preserve country codes and leading zeros. Widen date columns if Excel shows hash marks. Compare exported row counts with matching records, not merely the preview count.

---

## Reviewer demo guide

### Access

The product address is https://crm.bihtech.in and requires authentication. The proposed reviewer environment is https://demo.bihtech.in. That separate demo is planned; deployment and access have not been confirmed. Do not present it as an available evaluation link until it has been tested.

### Proposed sample environment

Use a separate database and configuration with fictional customers, staff, projects and transactions. Restrict demo users from production data, integration settings and destructive administration. Disable real outbound calls, WhatsApp, SMS, email and live ad actions; label simulated actions visibly. Use a data reset arrangement to keep the walkthrough repeatable.

Prepare sample records covering a new lead, follow-up, CNP, not interested after a visit, completed visit with a future follow-up, repeat visit and a sample post-sales case. These are demonstration scenarios, not claimed customer or traction numbers.

### Suggested five minute walkthrough

1. Introduce the customer problem and the product in about 30 seconds.
2. Open a sample lead and show its source, owner, current status and follow-up in about 60 seconds.
3. Review a completed visit and its timeline in about 60 seconds.
4. Show a customer who visited but is now in follow-up or not interested in about 45 seconds.
5. Preview and export the relevant report, explaining repeat visit rows, in about 45 seconds.
6. Close with configured additional modules and clearly labelled roadmap AI in about 60 seconds.

### Reviewer checks

The demo is ready when the entry link works in a fresh browser session, access instructions are clear, the sample journey is populated and the allowed role can complete it. Confirm that downloads contain only fictional data and that no demonstration action contacts a real person. Provide a 3 to 5 minute recording as a fallback for reviewers who cannot access the app.

---

## Architecture and integration approach

The local project uses a Laravel 10 PHP application with server-side business workflows, models and controllers. Its dependency manifest includes Sanctum, Google API client libraries, Firebase integration, web push, Pusher and spreadsheet tooling. Dependency presence does not establish active use or production configuration.

The product flow is organised around intake, stored CRM records, assignment and activity workflows, then role-specific views and reports. Additional HR, finance and post-sales modules extend that operating context. External communication relies on configured providers and their accounts.

### Key record relationships

- A lead holds customer and sales context and can relate to multiple interactions and visits.
- A site visit represents a dated activity with its own status and assigned user.
- A follow-up represents a next action; it must be interpreted alongside the customer history.
- A post-sales case connects booking-related work with demands, documents and payments where configured.
- An employee or user participates in roles, tasks and relevant HR workflows.

### Integration validation

Validate source mapping, authentication, duplicate handling, ownership selection and failure behaviour for each connector. Confirm that a test enquiry reaches the expected lead and that retrying a delivery does not produce unintended extra records. Validate provider delivery separately from internal notification creation.

### Data access and handling

Authentication and role controls exist in the application. Public-demo isolation, permission restrictions, file access, reset behaviour and external-message disabling still need environment-specific checks. This guide does not assert a completed security audit or an independently certified privacy programme.

Share documentation and screenshots without real customer records, employee financial details, secrets or server credentials. A public showcase repository can contain this guide, sanitised screenshots and published demo links without exposing the application source or database.

---

## AI roadmap and evaluation

Brickly's current workflow automation should be described as rules and application logic. Planned AI decision support includes lead scoring, conversation summaries, next-action suggestions and revenue or risk insights. These are roadmap items rather than verified live AI capabilities.

### Proposed first AI pilot

Select one narrow use case, such as drafting a summary from an authorised customer interaction. Compare its output against a human-reviewed reference. Check factual accuracy, missing commitments, incorrect dates and whether the user can correct the result before it changes a customer record or communication.

Future lead scoring requires outcome definitions and relevant historical data. Measure performance against a simple baseline and evaluate whether scores help salespeople make better decisions. Do not imply that accumulated data automatically produces reliable predictions.

### Product priorities

The immediate priority is a repeatable demonstration of the core workflow with reliable reports. The next stage is validation of connected modules and integrations. AI work should follow a specific use case, suitable data access and explicit quality checks. Priorities may change with pilot feedback; no fixed delivery dates are promised.

### Incubation objectives

Proposed five-week sprint objectives are to validate a priority workflow with users and prototype one AI use case. Proposed three-month incubation goals are structured pilots, improved reliability, and validation of onboarding and willingness to pay. These are goals, not completed results or guaranteed programme offerings.

## Supporting materials and contact

- Website: https://bihtech.in
- Current product: https://crm.bihtech.in — authentication required.
- Separate demo: https://demo.bihtech.in — planned, access pending validation.
- Pitch deck: 12 slide images prepared; compiled submission file remains a separate deliverable.
- Demo video: To be recorded and linked before final submission.
- GitHub showcase: Optional; publish a verified repository link once available.
- Founder and product contact: Vivek Kumar Verma, +91 93692 05635.

For evaluation, request access through the founder. An email address is intentionally omitted until a valid address is confirmed.
