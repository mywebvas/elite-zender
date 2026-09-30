<p>This explains what {{ config('platform.name') }} does with personal data. It covers two different groups of people, and the distinction matters.</p>

<h2>1. You, our customer</h2>
<p><strong>What we hold:</strong> your name, email address, hashed password, workspace name, billing country, plan and invoice history, and security metadata such as the IP address and browser recorded against sign-in and account changes.</p>
<p><strong>Why:</strong> to give you an account, to bill you, and to detect account takeover.</p>
<p><strong>Basis:</strong> performance of our contract with you, and our legitimate interest in keeping accounts secure.</p>

<h2>2. Your contacts</h2>
<p>For the people on your lists, <strong>you are the data controller and we are your processor.</strong> We hold what you upload — typically an email address, a name and any custom fields — plus engagement events (opens, clicks, bounces, complaints, unsubscribes) generated when you send to them.</p>
<p>We process that data only to carry out your instructions: to send your campaigns and report on them. We do not use your contacts for our own purposes, do not sell or share them, and do not contact them except as part of a campaign you send.</p>

<h2>Payment data</h2>
<p>Card details never reach our servers. Payments are processed by Paystack and Stripe, who hold the card. We store a reusable token, the card brand, the last four digits and the expiry month so renewals work and we can warn you before a card expires.</p>

<h2>Suppression records</h2>
<p>When somebody unsubscribes, hard-bounces or reports a message as spam, we record a one-way cryptographic hash of their address — never the address itself. This is how an opt-out is honoured permanently, and it is why suppression records survive even when a workspace is deleted: the promise was made to the recipient, not to the account holder.</p>

<h2>How long we keep things</h2>
<ul>
    <li><strong>Workspace data:</strong> for as long as the workspace exists. Deleting a workspace removes it permanently after a short cooling-off window.</li>
    <li><strong>Data exports:</strong> deleted automatically seven days after they are generated.</li>
    <li><strong>Audit and activity logs:</strong> retained on a rolling window for security and support.</li>
    <li><strong>Invoices:</strong> retained as long as tax law requires.</li>
</ul>

<h2>Your rights</h2>
<p>You can export everything in your workspace, and delete the workspace entirely, from <strong>Settings &rarr; Your data</strong> — no request or ticket needed. You also have the right to correct your data, to object to processing, and to complain to your data protection authority.</p>
<p>If one of <em>your</em> contacts exercises their rights with us directly, we will point them to you, because you are their controller — and we will help you action it.</p>

<h2>Sub-processors</h2>
<p>We use a hosting provider, a database and cache provider, and the payment processors named above. Your own SMTP relays are yours: we hold the credentials encrypted and use them only to send your mail.</p>

<h2>Cookies</h2>
<p>We set a session cookie and a CSRF cookie. Both are strictly necessary to sign you in and to keep the application secure. We do not run third-party advertising or analytics trackers on the application.</p>

<h2>Security</h2>
<p>Data is encrypted in transit. Relay passwords, payment tokens and mailbox credentials are encrypted at rest. Access is scoped per workspace and enforced at the database-query level as well as in the application.</p>
