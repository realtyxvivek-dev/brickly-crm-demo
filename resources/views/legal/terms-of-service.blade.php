@extends('legal.layout')

@section('title', 'Terms of Service')

@section('content')
<h1>Terms of Service</h1>
<p class="muted">Last updated: July 9, 2026</p>

<p>These Terms of Service apply to {{ brand_name() }} CRM features and Meta integrations, including Facebook Lead Ads, Facebook Login for Business, Page webhooks, Instagram automation, WhatsApp Business Platform / Meta WhatsApp Cloud API, templates, campaigns, and CRM lead handling.</p>

<h2>Authorized Use</h2>
<p>The system may only be used by authorized CRM users and administrators. You may connect only Meta business portfolios, Facebook Pages, Instagram professional accounts, WhatsApp Business Accounts, and phone numbers that you are authorized to manage.</p>

<h2>Meta and WhatsApp Policy Compliance</h2>
<ul>
    <li>You are responsible for complying with Meta Platform Terms, WhatsApp Business Terms, WhatsApp Business Messaging Policy, WhatsApp Commerce Policy, and applicable laws.</li>
    <li>WhatsApp template messages, bulk campaigns, and automation must be sent only to recipients where you have a valid lawful basis or required consent.</li>
    <li>Do not send misleading, abusive, prohibited, or spam content.</li>
    <li>Do not attempt to bypass Meta rate limits, messaging limits, account quality restrictions, template review, or opt-out requirements.</li>
</ul>

<h2>CRM Lead Data</h2>
<p>Lead and customer data must be used only for legitimate business follow-up, sales operations, support workflows, reporting, and authorized CRM activity. Users are responsible for handling personal data according to applicable privacy and communication laws.</p>

<h2>Templates and Bulk Campaigns</h2>
<p>WhatsApp templates created or synced through {{ brand_name() }} are subject to Meta review and approval. Campaign delivery depends on the connected WABA, phone number registration, template approval, recipient eligibility, payment method, account quality, and Meta API availability.</p>

<h2>Billing and Payment</h2>
<p>WhatsApp Business Platform messaging charges, conversation charges, and payment method requirements are managed by Meta. {{ brand_name() }} does not control Meta billing decisions, pricing, payment account approval, or payment method availability.</p>

<h2>Tokens and Account Security</h2>
<p>Admins are responsible for maintaining valid access tokens, app permissions, webhook settings, phone number registration, two-step verification PINs, and connected asset permissions. Revoke or rotate tokens when access is no longer required.</p>

<h2>Service Availability</h2>
<p>Meta API access depends on Meta Platform availability, connected account permissions, token validity, webhook delivery, app status, business verification, payment setup, and account quality. We do not guarantee uninterrupted operation of third-party APIs.</p>

<h2>Restrictions</h2>
<ul>
    <li>Do not use the system for unauthorized scraping, spam, harassment, fraud, or policy-violating campaigns.</li>
    <li>Do not connect or use business assets you are not authorized to manage.</li>
    <li>Do not upload or process data without required authorization or consent.</li>
</ul>

<h2>Changes</h2>
<p>These terms may be updated as the CRM and Meta integration features evolve.</p>

<h2>Contact</h2>
<p>For questions about these terms, contact: <a href="mailto:support@bihtech.in">support@bihtech.in</a>.</p>
@endsection
