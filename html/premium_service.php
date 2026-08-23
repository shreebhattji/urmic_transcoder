<?php
/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md
*/

include 'header.php'; ?>

<div class="containerindex">

    <div class="premium-hero">
        <h1>Dedicated Streaming Plan</h1>
        <p>Enterprise-grade hosted cloud infrastructure built for high-throughput broadcasts</p>
    </div>

    <div class="grid">

        <!-- Dedicated Plan Card -->
        <div class="card plan-card">
            <div>
                <div class="plan-header">
                    <div class="plan-title">Dedicated Streaming</div>
                    <div class="plan-price">₹4,000 <span style="font-size:16px;font-weight:normal;color:#94a3b8">/ month</span></div>
                    <span class="plan-badge">Recommended for Events & High Scale</span>
                </div>

                <ul class="feature-list">
                    <li class="feature-item">
                        <span class="check">✓</span>
                        <div><strong>Delivery Formats:</strong> HLS (m3u8), RTMP, SRT, DASH (Unlimited link data)</div>
                    </li>
                    <li class="feature-item">
                        <span class="check">✓</span>
                        <div><strong>Dedicated Bandwidth:</strong> High sustained throughput up to 10Gbps burst</div>
                    </li>
                    <li class="feature-item">
                        <span class="check">✓</span>
                        <div><strong>Custom Domain:</strong> Dedicated domain included (e.g., <code>yourbrand.live</code>)</div>
                    </li>
                    <li class="feature-item">
                        <span class="check">✓</span>
                        <div><strong>Automated SSL:</strong> Free Let's Encrypt SSL with automated renewals</div>
                    </li>
                    <li class="feature-item">
                        <span class="check">✓</span>
                        <div><strong>DDoS & Attack Protection:</strong> Network-level mitigation & Web Application Firewall</div>
                    </li>
                    <li class="feature-item">
                        <span class="check">✓</span>
                        <div><strong>Reserved Static IP:</strong> Dedicated IPv4 and IPv6 available for whitelisting</div>
                    </li>
                    <li class="feature-item">
                        <span class="check">✓</span>
                        <div><strong>Uptime Guarantee:</strong> 99.9% SLA with priority support</div>
                    </li>
                    <li class="feature-item">
                        <span class="check">✓</span>
                        <div><strong>Support:</strong> 24/7 Priority support & dedicated onboarding</div>
                    </li>
                </ul>
            </div>

            <div class="plan-actions">
                <a class="cta cta-primary" href="contact_us.php">Contact Us</a>
                <a class="cta cta-ghost" href="https://urmic.org/trusted-partners/" target="_blank">Our ISP Partners</a>
            </div>
        </div>

        <!-- Benefits Section -->
        <div class="card benefits-box">
            <h3>Why choose hosted streaming over a raw ISP static IP?</h3>

            <ul class="benefits-list">
                <li><strong>DDoS & Attack Protection:</strong> Network-level mitigation and WAF absorb large-scale attacks before reaching your origin.</li>
                <li><strong>Scalable Bandwidth & CDN:</strong> Globally distributed edge points scale to thousands of viewers without saturating your link.</li>
                <li><strong>High Availability & SLA:</strong> Redundant infrastructure guarantees 99.9% uptime for critical broadcasts.</li>
                <li><strong>Managed SSL & DNS:</strong> Automated SSL renewals and dedicated domain routing remove operational friction.</li>
                <li><strong>Security Isolation:</strong> Isolated cloud resources prevent cross-tenant interference.</li>
                <li><strong>24/7 Priority Support:</strong> Continuous application-level monitoring and expert stream support.</li>
            </ul>

            <div class="info-note">
                <strong>Billing & ISP Partnership:</strong> All servers are hosted with our CDN ISP partners. This hybrid model delivers the lowest latency and maximum stability for broadcasters. Billing is handled directly by the ISP partner (Includes GST; 2 months free on annual plans).
            </div>
        </div>

    </div>

</div>

<?php include 'footer.php'; ?>