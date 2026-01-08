<?php include 'header.php'; ?>
<style>
    :root {
        --accent: #0b74de;
        --muted: #6b7280;
        --card: #ffffff;
        --bg: #f3f4f6
    }

    body {
        font-family: Inter, system-ui, Arial, Helvetica, sans-serif;
        background: var(--bg);
        color: #111;
        margin: 0;
        padding: 32px
    }

    .wrap {
        max-width: 1100px;
        margin: 0 auto
    }

    header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 20px
    }

    header h1 {
        margin: 0;
        font-size: 20px
    }

    .cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 16px
    }

    .card {
        background: var(--card);
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
        padding: 20px
    }

    .price {
        font-size: 28px;
        font-weight: 700;
        color: var(--accent)
    }

    .muted {
        color: var(--muted);
        font-size: 13px
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px
    }

    th,
    td {
        padding: 10px;
        border-bottom: 1px solid #eef2f6;
        text-align: left;
        font-size: 14px
    }

    th {
        background: transparent;
        font-weight: 600
    }

    .feature {
        display: flex;
        align-items: center
    }

    .pill {
        display: inline-block;
        background: #eef6ff;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 13px;
        margin-left: auto
    }

    .cta {
        display: inline-block;
        padding: 10px 14px;
        border-radius: 10px;
        font-weight: 600;
        text-decoration: none
    }

    .cta-primary {
        background: var(--accent);
        color: #fff
    }

    .cta-ghost {
        border: 1px solid #e6eefa;
        color: var(--accent)
    }

    .benefits {
        margin-top: 18px
    }

    .note {
        font-size: 13px;
        color: #374151;
        background: #fff;
        padding: 12px;
        border-radius: 8px
    }

    footer {
        margin-top: 20px;
        font-size: 13px;
        color: var(--muted)
    }

    @media (max-width:600px) {
        header {
            flex-direction: column;
            align-items: flex-start
        }

        header h1 {
            margin-bottom: 8px
        }
    }
</style>

<body>

    <div class="wrap">

        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <section class="benefits">
            <div class="card">
                <h3 style="margin-top:0">Why choose hosted streaming over just buying a static IP from your ISP ?</h3>
                <ul style="margin:8px 0 0 18px">
                    <li><strong>DDoS & attack protection:</strong> Professional hosts run network-level DDoS mitigation and web application firewalls (WAF) that absorb and block large-scale attacks before they reach your origin server.</li>
                    <li><strong>Scalable bandwidth & CDN:</strong> Hosting + CDN provides globally distributed edge points and the ability to scale to many thousands of viewers without saturating a single home/office link.</li>
                    <li><strong>Higher availability & SLA:</strong> Providers operate redundant infrastructure and SLAs that keep streams online even when single links or hardware fail.</li>
                    <li><strong>Managed SSL, domain & DNS:</strong> Automated SSL issuance/renewal (Let's Encrypt), DNS features and a dedicated domain remove operational friction compared to configuring services on a raw ISP IP.</li>
                    <li><strong>Security isolation:</strong> Dedicated servers and hosting accounts isolate your traffic and services from other customers, reducing risks that come with shared consumer-grade network equipment.</li>
                    <li><strong>Monitoring & support:</strong> 24/7 monitoring, alerting and expert support are part of hosting plans — ISPs rarely provide application-level stream support.</li>
                    <li><strong>Optional reserved (static) IPs:</strong> If you still need a static IP for whitelisting, we can provision a reserved IP on a dedicated plan and keep it behind our mitigation/CDN layer.</li>
                </ul>

                <div class="note" style="margin-top:12px">
                    <strong>Quick notes:</strong> "Unlimited data for links" refers to stream delivery (no per-GB charge on the plan level for the specified formats). Extremely large egress (multi-TB per month) or abusive usage may require a custom enterprise agreement. CDN bandwidth, archival storage and advanced security may be subject to fair-use or tiered pricing.
                </div>
                <div class="note" style="margin-top:12px">
                    <strong>Hosting :</strong> All servers are hosted with our CDN ISP partners. This project aims to transform ISPs into data-center service providers through a hybrid partnership model. All billing is handled directly by the ISP. We found this is lowest letency and stable solutions for broadcastors . Price includes GST and 2 month will be free on yearly payment .
                </div>
            </div>
        </section>

        <div class="cards">
            <!-- Shared Streaming -->
            <div class="card">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                    <div>
                        <div class="muted">Shared Streaming</div>
                        <div class="price">₹2,000 / month</div>
                    </div>
                    <div style="margin-left:auto;text-align:right">
                        <div class="pill">Best for small producers</div>
                    </div>
                </div>


                <table>
                    <tr>
                        <th>Feature</th>
                        <th>Included</th>
                    </tr>
                    <tr>
                        <td>Delivery formats</td>
                        <td>HLS (m3u8), RTMP, SRT, DASH — unlimited data for links</td>
                    </tr>
                    <tr>
                        <td>Bandwidth</td>
                        <td>Shared pool — burst-capable (fair-usage policy)</td>
                    </tr>
                    <tr>
                        <td>Domain</td>
                        <td>Subdomain (example.customer.example.com)</td>
                    </tr>
                    <tr>
                        <td>SSL</td>
                        <td>Let's Encrypt (shared certificate)</td>
                    </tr>
                    <tr>
                        <td>Support</td>
                        <td>Email & chat (business hours)</td>
                    </tr>
                    <tr>
                        <td>Uptime SLA</td>
                        <td>99.5%</td>
                    </tr>
                </table>


                <div style="margin-top:12px;display:flex;gap:8px">
                    <a class="cta cta-primary" href="contact_us.php">Contact Us</a>
                    <a class="cta cta-ghost" href="https://urmic.org/trusted-partners/">Our ISP Partners</a>
                </div>
            </div>


            <!-- Dedicated Streaming -->
            <div class="card">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                    <div>
                        <div class="muted">Dedicated Streaming</div>
                        <div class="price">₹4,000 / month</div>
                    </div>
                    <div style="margin-left:auto;text-align:right">
                        <div class="pill">Recommended for events & scale</div>
                    </div>
                </div>


                <table>
                    <tr>
                        <th>Feature</th>
                        <th>Included</th>
                    </tr>
                    <tr>
                        <td>Delivery formats</td>
                        <td>HLS (m3u8), RTMP, SRT, DASH — unlimited data for links</td>
                    </tr>
                    <tr>
                        <td>Bandwidth</td>
                        <td>Dedicated bandwidth allocation (higher sustained throughput up to 10gbe spike )</td>
                    </tr>
                    <tr>
                        <td>Domain</td>
                        <td>Dedicated domain included (example: yourbrand.live)</td>
                    </tr>
                    <tr>
                        <td>SSL</td>
                        <td>Free SSL certificate (Let's Encrypt) + automated renewals</td>
                    </tr>
                    <tr>
                        <td>DDoS / Attack protection</td>
                        <td>Network-level mitigation & WAF</td>
                    </tr>
                    <tr>
                        <td>Static IP</td>
                        <td>Dedicated ip ipv4 and ipv6 available (reserved IP) — useful for whitelisting</td>
                    </tr>
                    <tr>
                        <td>Uptime SLA</td>
                        <td>99.9% with priority support</td>
                    </tr>
                    <tr>
                        <td>Support</td>
                        <td>24/7 priority support & onboarding</td>
                    </tr>
                </table>


                <div style="margin-top:12px;display:flex;gap:8px">
                    <a class="cta cta-primary" href="contact_us.php">Contact Us</a>
                    <a class="cta cta-ghost" href="https://urmic.org/trusted-partners/">Our ISP Partners</a>
                </div>
            </div>
        </div>
        <br>
        <br>
        <br>
        <footer>
            <div class="muted">Need an exportable copy of this pricing page or custom branding? Contact sales for a tailored quote and SLA.</div>
        </footer>
    </div>
</body>
<?php include 'footer.php'; ?>