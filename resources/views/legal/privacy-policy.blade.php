@extends('legal.layout')

@section('title', 'Privacy Policy')

@section('content')
<h1>Privacy Policy</h1>
<p class="muted">Last updated: July 9, 2026</p>

<p>This Privacy Policy explains how {{ brand_name() }} collects, uses, stores, and protects information for CRM features and Meta integrations, including Facebook Lead Ads, Facebook Login for Business, Page webhooks, Instagram professional accounts, and the WhatsApp Business Platform / Meta WhatsApp Cloud API.</p>

<h2>Information We Collect</h2>
<ul>
    <li>CRM user and business account details, such as name, email, role, phone number, and business profile information.</li>
    <li>Customer and lead contact information submitted through CRM forms, Facebook Lead Ads, WhatsApp messages, Instagram conversations, or authorized manual uploads.</li>
    <li>Meta connection identifiers and metadata, including business account ID, Page ID, form ID, leadgen ID, timestamps, webhook event IDs, and API response metadata.</li>
    <li>Instagram professional account details and interaction metadata when an authorized admin connects Instagram automation.</li>
    <li>WhatsApp Business Platform details, including WABA ID, phone number ID, business account ID, template names, languages, statuses, template components, recipient phone numbers, campaign logs, delivery statuses, opt-out preferences, and webhook events.</li>
    <li>Inbound and outbound communication records, including message status events, approved template sends, replies, call/callback events where enabled, automation logs, and support/follow-up activity.</li>
</ul>

<h2>How We Use Information</h2>
<ul>
    <li>To connect authorized Meta business assets to {{ brand_name() }}.</li>
    <li>To receive Facebook Lead Ads and webhook events and create CRM leads for follow-up.</li>
    <li>To sync approved WhatsApp templates, send approved template messages, manage bulk campaigns, and track delivery status.</li>
    <li>To support Instagram and WhatsApp automation, customer replies, opt-out handling, missed-call/callback follow-up, and CRM activity timelines.</li>
    <li>To prevent duplicate leads, troubleshoot delivery issues, maintain audit logs, improve reliability, and protect system security.</li>
</ul>

<h2>Data Sharing</h2>
<p>We do not sell Meta, WhatsApp, Instagram, Facebook Page, Lead Ads, customer, or lead data. Data is used inside {{ brand_name() }} for CRM operations, customer follow-up, automation, support, audit, and reporting. We may share data with service providers required to operate the CRM and with Meta APIs only as needed to provide the connected integration features.</p>

<h2>Data Storage and Security</h2>
<p>Access tokens, app secrets, webhook payloads, and API logs are stored with restricted access. Tokens and secrets are protected or encrypted where supported by the application. CRM access is limited to authorized users and administrators.</p>

<h2>Retention</h2>
<p>We retain CRM, lead, campaign, webhook, and audit records only as long as needed for business operations, legal compliance, security, troubleshooting, and customer support.</p>

<h2>Data Deletion</h2>
<p>You can request deletion of data related to Facebook Lead Ads, Facebook Page connections, Instagram automation, WhatsApp Business Platform configuration, WhatsApp templates, campaigns, message logs, or CRM lead records by following the instructions on our <a href="{{ route('legal.data-deletion') }}">Data Deletion</a> page.</p>

<h2>Contact</h2>
<p>For privacy questions or deletion requests, contact: <a href="mailto:support@bihtech.in">support@bihtech.in</a>.</p>
@endsection
