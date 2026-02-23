<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();
$site_name = getSetting($conn, 'site_name', 'StreamFlix');

$page_title = "Terms of Use | {$site_name}";
$meta_description = "Read the Terms of Use for {$site_name}. Understand the rules and guidelines for using our streaming platform.";
$meta_keywords = "terms of use, terms and conditions, user agreement, legal";

include 'includes/header.php';
?>

<style>
.page-container {
    min-height: 100vh;
    background: #000;
    padding: 4rem 0;
}
.content-wrapper {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 1.5rem;
}
@media (min-width: 768px) {
    .content-wrapper {
        padding: 0 3rem;
    }
}
.page-title {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 1rem;
    color: #fff;
}
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
}
.last-updated {
    color: #9ca3af;
    font-size: 0.875rem;
    margin-bottom: 3rem;
}
.content-section {
    margin-bottom: 3rem;
}
.content-section h2 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #e50914;
}
.content-section h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #fff;
}
.content-section p {
    font-size: 1rem;
    line-height: 1.8;
    color: #d1d5db;
    margin-bottom: 1rem;
}
.content-section ul, .content-section ol {
    margin-left: 1.5rem;
    color: #d1d5db;
    line-height: 1.8;
    margin-bottom: 1rem;
}
.content-section li {
    margin-bottom: 0.5rem;
}
.highlight-box {
    background: rgba(229, 9, 20, 0.1);
    border-left: 4px solid #e50914;
    padding: 1.5rem;
    margin: 2rem 0;
    border-radius: 0.5rem;
}
.highlight-box p {
    margin: 0;
    color: #fca5a5;
}
</style>

<div class="page-container animate-in fade-in">
    <div class="content-wrapper">
        <h1 class="page-title">Terms of Use</h1>
        <p class="last-updated">Last Updated: <?php echo date('F j, Y'); ?></p>

        <div class="content-section">
            <h2>1. Acceptance of Terms</h2>
            <p>
                By accessing and using <?php echo htmlspecialchars($site_name); ?> ("the Service", "we", "us", or "our"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.
            </p>
        </div>

        <div class="content-section">
            <h2>2. Description of Service</h2>
            <p>
                <?php echo htmlspecialchars($site_name); ?> is an indexing platform that provides access to publicly available IPTV channels and streaming content. We do not broadcast, host, or store any streaming content. We simply index and organize publicly available content from open sources, including IPTV channel lists originally provided on GitHub and other publicly accessible platforms.
            </p>
        </div>

        <div class="highlight-box">
            <p>
                <strong>Important:</strong> <?php echo htmlspecialchars($site_name); ?> does not broadcast, host, or store any streaming content. We are purely an indexing service and do not accept any copyright or legal responsibility for the content accessed through our platform.
            </p>
        </div>

        <div class="content-section">
            <h2>3. User Responsibilities</h2>
            <p>
                By using our Service, you agree to:
            </p>
            <ul>
                <li>Use the Service only for lawful purposes and in accordance with these Terms</li>
                <li>Not use the Service to violate any applicable laws or regulations</li>
                <li>Not attempt to gain unauthorized access to any part of the Service</li>
                <li>Not interfere with or disrupt the Service or servers connected to the Service</li>
                <li>Respect intellectual property rights of content owners</li>
                <li>Not use the Service to transmit any harmful, offensive, or illegal content</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>4. Intellectual Property</h2>
            <p>
                All logos, channel names, brand identities, and other copyrighted materials displayed on our platform are the property of their respective owners. <?php echo htmlspecialchars($site_name); ?> does not claim ownership of any copyrighted material indexed on our platform.
            </p>
            <p>
                The Service itself, including its design, functionality, and code, is the property of <?php echo htmlspecialchars($site_name); ?> and is protected by copyright and other intellectual property laws.
            </p>
        </div>

        <div class="content-section">
            <h2>5. Disclaimer of Warranties</h2>
            <p>
                THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, OR NON-INFRINGEMENT.
            </p>
            <p>
                We do not warrant that:
            </p>
            <ul>
                <li>The Service will be uninterrupted, secure, or error-free</li>
                <li>The results obtained from using the Service will be accurate or reliable</li>
                <li>Any content accessed through the Service will be available, legal, or appropriate</li>
                <li>Defects or errors will be corrected</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>6. Limitation of Liability</h2>
            <p>
                TO THE MAXIMUM EXTENT PERMITTED BY LAW, <?php echo htmlspecialchars($site_name); ?> SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, OR ANY LOSS OF PROFITS OR REVENUES, WHETHER INCURRED DIRECTLY OR INDIRECTLY, OR ANY LOSS OF DATA, USE, GOODWILL, OR OTHER INTANGIBLE LOSSES, RESULTING FROM:
            </p>
            <ul>
                <li>Your use or inability to use the Service</li>
                <li>Any content accessed through the Service</li>
                <li>Unauthorized access to or alteration of your data</li>
                <li>Any other matter relating to the Service</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>7. Copyright and DMCA</h2>
            <p>
                <?php echo htmlspecialchars($site_name); ?> respects the intellectual property rights of others. If you believe that your copyrighted work has been indexed on our platform in a way that constitutes copyright infringement, please contact us through our <a href="<?php echo BASE_URL; ?>/contact" class="text-netflix-red hover:underline">contact page</a> with the following information:
            </p>
            <ul>
                <li>A description of the copyrighted work you claim has been infringed</li>
                <li>The location of the material on our platform</li>
                <li>Your contact information</li>
                <li>A statement that you have a good faith belief that the use is not authorized</li>
                <li>A statement that the information is accurate and you are authorized to act on behalf of the copyright owner</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>8. Content Disclaimer</h2>
            <p>
                <?php echo htmlspecialchars($site_name); ?> does not control, monitor, or verify the content accessed through our platform. We do not endorse, support, or take responsibility for any content accessed through our Service. Users are solely responsible for ensuring that their use of any content complies with applicable laws and regulations.
            </p>
        </div>

        <div class="content-section">
            <h2>9. Modifications to Service</h2>
            <p>
                We reserve the right to modify, suspend, or discontinue the Service, or any part thereof, at any time with or without notice. We shall not be liable to you or any third party for any modification, suspension, or discontinuance of the Service.
            </p>
        </div>

        <div class="content-section">
            <h2>10. Changes to Terms</h2>
            <p>
                We reserve the right to modify these Terms of Use at any time. We will notify users of any material changes by updating the "Last Updated" date at the top of this page. Your continued use of the Service after such modifications constitutes acceptance of the updated Terms.
            </p>
        </div>

        <div class="content-section">
            <h2>11. Termination</h2>
            <p>
                We may terminate or suspend your access to the Service immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.
            </p>
        </div>

        <div class="content-section">
            <h2>12. Governing Law</h2>
            <p>
                These Terms shall be governed by and construed in accordance with applicable laws, without regard to its conflict of law provisions. Any disputes arising from these Terms or your use of the Service shall be resolved in accordance with applicable legal procedures.
            </p>
        </div>

        <div class="content-section">
            <h2>13. Contact Information</h2>
            <p>
                If you have any questions about these Terms of Use, please contact us through our <a href="<?php echo BASE_URL; ?>/contact" class="text-netflix-red hover:underline">contact page</a>.
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
