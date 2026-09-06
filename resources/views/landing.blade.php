<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ brand_name() }} brings real estate leads, calling, projects, post-sales, people and finance into one premium workspace.">
    <meta name="theme-color" content="#06110b">
    <title>{{ brand_name() }} — Real estate operations, unified</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @php($landingManifest = json_decode(file_get_contents(public_path('build/manifest.json')), true))
    <link rel="stylesheet" href="/build/{{ $landingManifest['resources/css/landing.css']['file'] }}">
    <script type="module" src="/build/{{ $landingManifest['resources/js/landing.js']['file'] }}"></script>
    <style>:root{--brand-start:{{ gradient_start_color() }};--brand-end:{{ gradient_end_color() }};}</style>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-nav" data-nav>
        <div class="nav-inner page-shell">
            <a class="brand" href="#top" aria-label="{{ brand_name() }} home"><span class="brand-mark" aria-hidden="true"><span></span></span><span>{{ brand_name() }}</span></a>
            <a class="button button-small button-light" href="#book-demo">Book Demo <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
        </div>
    </header>

    <main id="main-content">
        <section class="hero" id="top" aria-labelledby="hero-title">
            <div class="aurora" aria-hidden="true"><span class="aurora-core"></span></div>
            <div class="hero-orbit orbit-one" aria-hidden="true"></div><div class="hero-orbit orbit-two" aria-hidden="true"></div>
            <div class="hero-content page-shell">
                <div class="eyebrow reveal-item"><span class="status-dot"></span> Built for modern real estate teams</div>
                <h1 id="hero-title" class="hero-title" aria-label="One platform to run your entire real estate business">
                    <span class="headline-line"><span>One platform to run</span></span>
                    <span class="headline-line"><span>your entire <em>real estate</em></span></span>
                    <span class="headline-line"><span>business.</span></span>
                </h1>
                <p class="hero-copy reveal-item">Unify every lead, conversation, site visit, project, team and payment—without stitching together six different tools.</p>
                <div class="hero-actions reveal-item">
                    <a class="button button-primary" href="#book-demo">Book your demo <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    <span class="micro-proof"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Secure, role-based operations</span>
                </div>
                <div class="hero-stage" data-parallax="hero">
                    <div class="stage-glow" aria-hidden="true"></div>
                    <div class="hero-art-frame"><img src="/images/landing/brickly-ai-command-center.webp" alt="Illustrative {{ brand_name() }} command center with fictional pipeline and project-demand data" width="1536" height="1024"><div class="art-sheen" aria-hidden="true"></div></div>
                    <div class="floating-stat stat-leads"><span class="stat-icon"><i class="fa-solid fa-bolt"></i></span><span><small>Fresh lead response</small><strong>Under 60 sec</strong></span></div>
                    <div class="floating-stat stat-growth"><span class="stat-icon"><i class="fa-solid fa-chart-line"></i></span><span><small>Pipeline visibility</small><strong>Live & role-wise</strong></span></div>
                </div>
            </div>
        </section>

        <section class="integration-section" aria-labelledby="integrations-title"><div class="page-shell">
            <p class="section-kicker centered" id="integrations-title">Your lead channels. One clean pipeline.</p>
            <div class="integration-track" data-spotlight><div class="spotlight" aria-hidden="true"></div>
                @foreach ([['fa-brands fa-meta','Meta'],['fa-brands fa-whatsapp','WhatsApp'],['fa-brands fa-instagram','Instagram'],['fa-solid fa-table-cells-large','Google Sheets'],['fa-solid fa-building','99acres'],['fa-solid fa-phone-volume','IVR']] as [$icon,$label])
                    <div class="integration"><i class="{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></div>
                @endforeach
            </div>
        </div></section>

        <?php
            $modules = [
                ['lead-management', 'fa-solid fa-filter-circle-dollar', 'Lead Management', 'Convert every enquiry into a clearly owned next action.', 'Capture leads from every source, remove duplicate noise and keep your sales pipeline moving.', ['Automatic lead capture and assignment', 'Instant call and WhatsApp response workflows', 'Calling, recordings, follow-ups and meetings', 'Site visits and booking pipeline', 'Duplicate control, SLA reminders and escalations']],
                ['team-hr', 'fa-solid fa-people-group', 'Team & HR', 'Build a high-performing team on one accountable system.', 'Connect people operations with everyday execution, from hiring to attendance and performance.', ['Employee profiles and role permissions', 'Attendance, leave and payroll workflows', 'Hiring, onboarding and employee records', 'Tasks, targets and incentive tracking', 'Role-wise team performance reports']],
                ['lead-bank', 'fa-solid fa-database', 'Lead Bank', 'Turn dormant data into your next revenue opportunity.', 'Organise old, unassigned and protected leads so valuable demand never gets forgotten.', ['Smart folders, tags and saved segments', 'Unassigned and historical lead control', 'Duplicate merging and data clean-up', 'Lead reactivation and redistribution', 'Import, allocation and recall controls']],
                ['ads-quality', 'fa-solid fa-chart-simple', 'Ads & Quality', 'Know which campaigns bring customers—not just form fills.', 'Connect ad spend to lead quality, team response and eventual business outcomes.', ['Meta campaign and source performance', 'Qualified, duplicate and junk lead reports', 'Lead-quality feedback signals for Meta', 'Response-time and conversion analysis', 'Ads-to-booking ROI visibility']],
                ['projects-sharing', 'fa-solid fa-building-circle-check', 'Projects & Sharing', 'Turn every project into one intelligent selling link.', 'Share the complete property experience once, then learn what each buyer is interested in.', ['Brochure, price list, inventory and floor plans', 'One personalised project link instead of many files', 'Visit, return and time-spent analytics', 'Floor-plan and content interest signals', 'Next-pitch insight for every sales advisor']],
                ['finance', 'fa-solid fa-wallet', 'Finance', 'Bring company income, expenses and approvals under control.', 'Understand where money comes from, where it goes and what needs attention next.', ['Company income and expense tracking', 'Purchase orders and approval workflows', 'Employee reimbursements and payroll visibility', 'Project-wise cost and profitability view', 'Outstanding, collections and cash-flow signals']],
                ['post-sales', 'fa-solid fa-key', 'Post-Sales', 'Protect revenue and customer trust after every booking.', 'Keep payment commitments, documents and handover activities on schedule automatically.', ['Booking and customer lifecycle records', 'Payment and demand schedules', 'Automatic payment reminders', 'Overdue collections and ageing reports', 'Documents, handover and customer updates']],
                ['automation', 'fa-solid fa-wand-magic-sparkles', 'Automation', 'Make the right action happen without manual chasing.', 'Connect channels, teams and workflows with reliable reminders, rules and integrations.', ['Instant cloud calls and WhatsApp messages', 'Meta, Instagram and Google Sheets connections', 'Lead assignment and follow-up rules', 'Scheduled reports and daily email intelligence', 'Exception alerts and manager escalations']],
            ];
            $moduleScreens = [
                'lead-management' => 'brickly-ai-command-center.webp',
                'team-hr' => 'brickly-ai-operations-control.webp',
                'lead-bank' => 'brickly-ai-lead-intelligence.webp',
                'ads-quality' => 'brickly-ai-lead-intelligence.webp',
                'projects-sharing' => 'brickly-ai-project-sharing.webp',
                'finance' => 'brickly-ai-operations-control.webp',
                'post-sales' => 'brickly-ai-operations-control.webp',
                'automation' => 'brickly-ai-operations-control.webp',
            ];
        ?>
        <section class="section feature-section" id="product" aria-labelledby="features-title"><div class="page-shell">
            <div class="module-showcase-heading reveal-group"><p class="section-kicker">One connected platform</p><h2 id="features-title">Everything your real estate business <span>needs.</span></h2><p>From the first ad click to the final payment reminder, every team works with the same customer and business data.</p></div>
            <div class="module-explorer" data-module-explorer>
                <div class="module-tabs" role="tablist" aria-label="Business modules">
                    @foreach ($modules as $index => [$id, $icon, $title])
                        <button type="button" role="tab" id="tab-{{ $id }}" aria-controls="panel-{{ $id }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? '0' : '-1' }}" data-module-tab>
                            <i class="{{ $icon }}" aria-hidden="true"></i><span>{{ $title }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="module-panels">
                    @foreach ($modules as $index => [$id, $icon, $title, $headline, $description, $features])
                        <article class="module-panel" id="panel-{{ $id }}" role="tabpanel" aria-labelledby="tab-{{ $id }}" @if ($index !== 0) hidden @endif>
                            <div class="module-copy"><span class="module-index">0{{ $index + 1 }} / 08</span><h3>{{ $headline }}</h3><p>{{ $description }}</p><ul>@foreach ($features as $feature)<li><i class="fa-solid fa-check" aria-hidden="true"></i><span>{{ $feature }}</span></li>@endforeach</ul></div>
                            <figure class="module-visual"><img src="/images/landing/{{ $moduleScreens[$id] }}" alt="Illustrative {{ brand_name() }} {{ $title }} workspace using fictional demo data" width="1536" height="1024" loading="lazy"><figcaption><i class="{{ $icon }}" aria-hidden="true"></i><span>{{ $title }} workspace</span><small>Polished product preview · fictional data</small></figcaption></figure>
                        </article>
                    @endforeach
                </div>
            </div>
            <div class="module-flow" aria-label="Connected real estate lifecycle"><span>Attract</span><i class="fa-solid fa-arrow-right"></i><span>Capture</span><i class="fa-solid fa-arrow-right"></i><span>Convert</span><i class="fa-solid fa-arrow-right"></i><span>Collect</span><i class="fa-solid fa-arrow-right"></i><span>Grow</span></div>
        </div></section>

        <section class="section advantage-section" id="advanced-capabilities" aria-labelledby="advantage-title"><div class="page-shell">
            <div class="section-heading advantage-heading reveal-group"><div><p class="section-kicker">Beyond a traditional CRM</p><h2 id="advantage-title">Built for how real estate <span>actually sells.</span></h2></div><p>Give sales teams faster response tools, buyer-intent signals and management intelligence—without adding more disconnected software.</p></div>
            <div class="advantage-grid stagger-grid">
                <article class="advantage-card advantage-project">
                    <div class="advantage-copy"><span class="advantage-icon"><i class="fa-solid fa-link" aria-hidden="true"></i></span><p class="advantage-label">Smart Project Link</p><h3>Share once. Understand every buyer interaction.</h3><p>Send one branded link containing the brochure, price list, inventory, floor plans, gallery and project details. See what the buyer opened, revisited and explored longest so the next pitch starts with real intent.</p><ul><li>Link visits and return visits</li><li>Time spent by section</li><li>Most-viewed floor plans</li></ul></div>
                    <div class="project-insight" aria-label="Example project-link engagement summary"><div class="insight-bar"><span><i class="fa-solid fa-lock" aria-hidden="true"></i> share.brickly.app/residences</span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></div><div class="insight-preview"><span>Customer interest signal</span><strong>3 BHK · Floor plan A</strong><small>Highest engagement in this visit</small></div><div class="insight-metrics"><span><small>Total attention</small><strong>12m 48s</strong></span><span><small>Return visits</small><strong>03</strong></span><span><small>Next pitch</small><strong>3 BHK</strong></span></div></div>
                </article>

                <article class="advantage-card advantage-apps">
                    <span class="advantage-icon"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i></span><p class="advantage-label">Native apps & calling</p><h3>Your sales desk travels with the team.</h3><p>Work from the native desktop experience or Android mobile app. Use cloud calling, listen to available recordings and trigger an instant call or WhatsApp response as soon as a lead arrives.</p><div class="device-row" aria-label="Supported workspaces"><span><i class="fa-solid fa-desktop" aria-hidden="true"></i> Desktop</span><span><i class="fa-brands fa-android" aria-hidden="true"></i> Android</span><span><i class="fa-solid fa-cloud" aria-hidden="true"></i> Cloud calling</span></div><small class="support-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> On-device call recording is available on supported Android devices and OS versions.</small>
                </article>

                <article class="advantage-card advantage-meta">
                    <span class="advantage-icon"><i class="fa-brands fa-meta" aria-hidden="true"></i></span><p class="advantage-label">Meta Quality Loop</p><h3>Help Meta optimise for better leads.</h3><p>Send qualified, junk, duplicate and conversion outcomes back as quality signals, helping campaigns learn beyond the initial form submission.</p><div class="quality-loop" aria-label="Lead quality feedback flow"><span>Meta lead</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span>CRM outcome</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span>Quality signal</span></div>
                </article>

                <article class="advantage-card advantage-advisor">
                    <span class="advantage-icon"><i class="fa-solid fa-address-card" aria-hidden="true"></i></span><p class="advantage-label">Advisor Public Profile</p><h3>Give every sales person a trusted digital identity.</h3><p>Each advisor gets a public profile link where customers can verify their name, company, expertise and contact details before continuing the conversation.</p><div class="advisor-preview"><span class="advisor-avatar"><i class="fa-solid fa-user-tie" aria-hidden="true"></i></span><span><strong>Your Property Advisor</strong><small>Verified · {{ brand_name() }}</small></span><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                </article>

                <article class="advantage-card advantage-email">
                    <span class="advantage-icon"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i></span><p class="advantage-label">Daily Intelligence Email</p><h3>The day’s picture, delivered automatically.</h3><p>Leadership and users receive scheduled summaries for lead movement, follow-up risk, team activity, campaign quality and revenue signals.</p><div class="email-preview"><span><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Daily business brief</span><strong>08:00 AM</strong></div>
                </article>

                <article class="advantage-card advantage-intelligence">
                    <div class="advantage-copy"><span class="advantage-icon"><i class="fa-solid fa-table" aria-hidden="true"></i></span><p class="advantage-label">Data Intelligence Workspace</p><h3>Spreadsheet freedom with CRM-grade control.</h3><p>Explore live business data in an internal Google Sheets-like workspace—filter, group, compare and report without exporting sensitive records into separate files.</p><div class="intelligence-points"><span><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Advanced reporting</span><span><i class="fa-solid fa-filter" aria-hidden="true"></i> Live filters</span><span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Role-aware data</span></div></div>
                    <div class="sheet-preview" role="img" aria-label="Illustrative internal data intelligence sheet with campaign and lead-quality columns"><div class="sheet-toolbar"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i><span>Lead Quality Intelligence</span><small>Live</small></div><div class="sheet-grid"><span>Campaign</span><span>Leads</span><span>Qualified</span><span>CPL</span><strong>Premium Homes</strong><span>248</span><span class="positive">61%</span><span>₹428</span><strong>City Launch</strong><span>183</span><span class="positive">54%</span><span>₹512</span><strong>Investor Week</strong><span>96</span><span class="positive">68%</span><span>₹391</span></div></div>
                </article>
            </div>
        </div></section>

        <section class="section lifecycle-section" aria-labelledby="lifecycle-title"><div class="page-shell lifecycle-layout">
            <div class="lifecycle-copy reveal-group"><p class="section-kicker">Lead-to-revenue lifecycle</p><h2 id="lifecycle-title">No handoff gets <span>lost.</span></h2><p>One connected record follows the buyer journey. Every role sees exactly what they need—and the next action never disappears in a chat thread.</p><div class="proof-list"><span><i class="fa-solid fa-check"></i> Clear ownership at every stage</span><span><i class="fa-solid fa-check"></i> Automatic follow-up protection</span><span><i class="fa-solid fa-check"></i> Full activity and audit trail</span></div></div>
            <div class="lifecycle-visual stagger-grid" aria-label="Lead lifecycle stages">
                @foreach ([['01','Capture','Every enquiry enters cleanly'],['02','Qualify','Intent and budget get structured'],['03','Engage','Calls and follow-ups stay timely'],['04','Visit','Meetings become site visits'],['05','Close','Opportunity becomes booking'],['06','Collect','Post-sales protects revenue']] as [$step,$title,$copy])
                    <div class="lifecycle-step"><span>{{ $step }}</span><div><strong>{{ $title }}</strong><small>{{ $copy }}</small></div><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></div>
                @endforeach
            </div>
        </div></section>

        <section class="section control-section" aria-labelledby="control-title"><div class="control-pin page-shell">
            <div class="section-heading reveal-group"><div><p class="section-kicker">Operational control</p><h2 id="control-title">Know what drives <span>revenue.</span></h2></div><p>Live, privacy-safe signals help leaders spot momentum, protect follow-ups and coach teams before the month is over.</p></div>
            <figure class="control-dashboard product-capture" data-parallax="dashboard">
                <img src="/images/landing/brickly-ai-lead-intelligence.webp" alt="Illustrative {{ brand_name() }} lead-intelligence dashboard using fictional campaign data" width="1536" height="1024" loading="lazy">
                <figcaption><span><i class="fa-solid fa-circle-check"></i> Lead-quality intelligence</span><small>Polished product preview · fictional data</small></figcaption>
            </figure>
            <div class="control-notes stagger-grid"><div><i class="fa-solid fa-eye"></i><strong>One source of truth</strong><span>Live pipeline and team visibility.</span></div><div><i class="fa-solid fa-gauge-high"></i><strong>Faster decisions</strong><span>Exception-led operational signals.</span></div><div><i class="fa-solid fa-lock"></i><strong>Privacy by role</strong><span>Workspaces shaped by responsibility.</span></div></div>
        </div></section>

        <section class="section roles-section" aria-labelledby="roles-title"><div class="page-shell roles-layout">
            <div class="role-panel" data-role-panel><div class="role-tabs" role="tablist" aria-label="Role workspaces">@foreach (['Leadership','Sales','CRM','Post-Sales','HR & Finance'] as $index=>$role)<button type="button" role="tab" aria-selected="{{ $index===0 ? 'true' : 'false' }}" data-role-tab="{{ $index }}">{{ $role }}</button>@endforeach</div>
                <div class="role-screen"><img src="/images/landing/brickly-ai-operations-control.webp" alt="Illustrative role-based {{ brand_name() }} operations workspace using fictional data" width="1536" height="1024" loading="lazy"><div class="role-caption"><span data-role-eyebrow>Command center</span><h3 data-role-title>See the whole business clearly.</h3><p data-role-copy>Pipeline, performance, risk and revenue—summarised for fast decisions.</p></div></div>
            </div>
            <div class="roles-copy reveal-group"><p class="section-kicker">Role-based workspaces</p><h2 id="roles-title">Focused for each role. <span>Connected for everyone.</span></h2><p>Give every team a purposeful workspace without fragmenting your data. Permissions, priorities and performance stay aligned.</p><a href="#book-demo" class="text-link">See {{ brand_name() }} for your team <i class="fa-solid fa-arrow-right"></i></a></div>
        </div></section>

        <section class="section pricing-section" id="pricing" aria-labelledby="pricing-title"><div class="page-shell">
            <div class="pricing-heading reveal-group"><p class="section-kicker">Indicative pricing</p><h2 id="pricing-title">Start lean. Scale without <span>limits.</span></h2><p>Final pricing may vary based on users, integrations, data migration and implementation scope.</p></div>
            <div class="pricing-grid stagger-grid">
                @foreach ([
                    ['Starter','₹1,499','/ month','For small teams establishing a dependable sales process.',['Lead capture & assignment','Calling and follow-ups','Core reports'],false],
                    ['Growth','₹4,999','/ month','For growing teams that need automation and deeper control.',['Everything in Starter','Automation workflows','Projects & integrations','Role-based dashboards'],true],
                    ['Enterprise','Custom','','For multi-team businesses with tailored workflows and rollout.',['Everything in Growth','Post-sales, HR & Finance','Custom implementation','Priority support'],false]
                ] as [$name,$price,$unit,$copy,$features,$popular])
                    <article class="price-card {{ $popular ? 'featured' : '' }}">@if ($popular)<span class="popular-badge">Most popular</span>@endif<p>{{ $name }}</p><div class="price"><strong>{{ $price }}</strong><span>{{ $unit }}</span></div><small>{{ $copy }}</small><ul>@foreach ($features as $feature)<li><i class="fa-solid fa-check"></i>{{ $feature }}</li>@endforeach</ul><a class="button {{ $popular ? 'button-primary' : 'button-outline' }}" href="#book-demo">Book Demo</a></article>
                @endforeach
            </div>
        </div></section>

        <section class="section demo-section" id="book-demo" aria-labelledby="demo-title"><div class="demo-glow" aria-hidden="true"></div><div class="page-shell demo-layout">
            <div class="demo-copy reveal-group"><p class="section-kicker">See it in your workflow</p><h2 id="demo-title">Your real estate business, finally in <span>one place.</span></h2><p>Tell us a little about your team. We’ll walk you through the flows that matter most to your business.</p><div class="demo-points"><span><i class="fa-solid fa-phone"></i><a href="tel:+919369205635">+91 93692 05635</a></span><span><i class="fa-solid fa-envelope"></i><a href="mailto:support@bihtech.in">support@bihtech.in</a></span><span><i class="fa-solid fa-route"></i> Clear rollout plan</span></div></div>
            <form class="demo-form" action="{{ route('demo-request.store') }}" method="post">@csrf
                <div class="honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                @if (session('demo_success'))<div class="form-alert success" role="status"><i class="fa-solid fa-circle-check"></i>{{ session('demo_success') }}</div>@endif
                @if ($errors->has('demo_request'))<div class="form-alert error" role="alert"><i class="fa-solid fa-circle-exclamation"></i>{{ $errors->first('demo_request') }}</div>@endif
                <div class="form-grid">
                    <div class="field"><label for="name">Your name</label><input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Full name" aria-describedby="name-error">@error('name')<span id="name-error" class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="company">Company</label><input id="company" name="company" value="{{ old('company') }}" required autocomplete="organization" placeholder="Company name" aria-describedby="company-error">@error('company')<span id="company-error" class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="email">Work email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@company.com" aria-describedby="email-error">@error('email')<span id="email-error" class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="phone">Phone</label><input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="+91 98765 43210" aria-describedby="phone-error">@error('phone')<span id="phone-error" class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field field-full"><label for="message">What should we focus on? <span>Optional</span></label><textarea id="message" name="message" rows="4" placeholder="Your team size, current challenges or priority modules">{{ old('message') }}</textarea>@error('message')<span class="field-error">{{ $message }}</span>@enderror</div>
                </div>
                <button class="button button-primary form-submit" type="submit">Request my demo <i class="fa-solid fa-arrow-right"></i></button><p class="form-note">By submitting, you agree to be contacted about {{ brand_name() }}. Your data is used only for this request.</p>
            </form>
        </div></section>
    </main>

    <footer class="site-footer"><div class="page-shell footer-inner"><a class="brand" href="#top"><span class="brand-mark"><span></span></span><span>{{ brand_name() }}</span></a><p><a href="tel:+919369205635">+91 93692 05635</a> · <a href="mailto:support@bihtech.in">support@bihtech.in</a></p><nav aria-label="Legal"><a href="{{ route('legal.privacy') }}">Privacy</a><a href="{{ route('legal.terms') }}">Terms</a><a href="{{ route('legal.data-deletion') }}">Data deletion</a></nav><small>© {{ date('Y') }} {{ brand_name() }}. All rights reserved.</small></div></footer>
</body>
</html>
