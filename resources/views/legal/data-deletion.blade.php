@extends('legal.layout')

@section('title', 'Data Deletion')

@section('content')
<h1>Data Deletion Instructions</h1>
<p class="muted">Last updated: July 9, 2026</p>

<p>Use this page as the Meta App Settings &rarr; User data deletion &rarr; Data deletion instructions URL. If Meta asks for a callback URL and you do not have a callback implementation, choose <strong>Data deletion instructions URL</strong> and use this page.</p>

<p>You can request deletion of data stored by {{ brand_name() }} for CRM and Meta integrations, including Facebook Lead Ads, Facebook Page connections, Instagram automation, WhatsApp Business Platform / Meta WhatsApp Cloud API, templates, campaigns, webhook logs, and CRM lead records.</p>

<h2>How To Request Deletion</h2>
<p>Send an email to <a href="mailto:support@bihtech.in">support@bihtech.in</a> with the subject line:</p>

<p><strong>Meta/WhatsApp Data Deletion Request</strong></p>

<p>Please include:</p>
<ul>
    <li>Your name, business name, email address, and phone number.</li>
    <li>The Facebook Page name or Page ID, Instagram username, WhatsApp Business Account ID, WABA ID, phone number ID, or WhatsApp phone number if known.</li>
    <li>The lead/customer details you submitted, such as phone number, email, city, project interest, or campaign context.</li>
    <li>The approximate date of the lead form submission, WhatsApp message, campaign message, Instagram comment/direct message, or webhook interaction.</li>
</ul>

<h2>What Can Be Deleted</h2>
<ul>
    <li>Facebook Page connection records and Lead Ads webhook records linked to your authorized business connection.</li>
    <li>CRM lead records created from Facebook Lead Ads, Instagram conversations, WhatsApp messages, manual imports, or campaigns, where legally and operationally permitted.</li>
    <li>Instagram scoped data, automation logs, direct message flow records, and related webhook/API logs.</li>
    <li>WhatsApp Business Platform configuration records, WABA connection data, templates synced into the CRM, campaign records, message status logs, opt-out preferences, webhook logs, and call/callback event logs where legally and technically feasible.</li>
    <li>Access tokens and connected account credentials when an admin disconnects the related integration.</li>
</ul>

<h2>Processing Time</h2>
<p>We will review and process valid deletion requests generally within 30 days. Some records may be retained where required for legal compliance, security, fraud prevention, dispute handling, billing, audit, or operational obligations.</p>

<h2>Connected Account Removal</h2>
<p>An authorized CRM admin can disconnect Facebook, Instagram, or WhatsApp integrations from the CRM. Disconnecting stops new webhook or automation activity for that account and clears saved connection credentials where applicable.</p>

<h2>Contact</h2>
<p>For deletion support, contact: <a href="mailto:support@bihtech.in">support@bihtech.in</a>.</p>
@endsection
