<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();
$site_name = getSetting($conn, 'site_name', 'StreamFlix');

$page_title = "Privacy Policy | {$site_name}";
$meta_description = "Read the Privacy Policy for {$site_name}. Learn how we collect, use, and protect your personal information.";
$meta_keywords = "privacy policy, data protection, user privacy, personal information";

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
    background: rgba(16, 185, 129, 0.1);
    border-left: 4px solid #10b981;
    padding: 1.5rem;
    margin: 2rem 0;
    border-radius: 0.5rem;
}
.highlight-box p {
    margin: 0;
    color: #6ee7b7;
}
</style>

<div class="page-container animate-in fade-in">
    <div class="content-wrapper">
        <h1 class="page-title">Privacy Policy</h1>
        <p class="last-updated">Last Updated: <?php echo date('F j, Y'); ?></p>

        <div class="content-section">
            <h2>1. Introduction</h2>
            <p>
                At <?php echo htmlspecialchars($site_name); ?> ("we", "us", or "our"), we respect your privacy and are committed to protecting your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our Service.
            </p>
        </div>

        <div class="content-section">
            <h2>2. Information We Collect</h2>
            
            <h3>2.1 Information You Provide</h3>
            <p>
                We may collect information that you voluntarily provide to us when you:
            </p>
            <ul>
                <li>Register for an account</li>
                <li>Use our Service</li>
                <li>Contact us through our contact form</li>
                <li>Subscribe to our newsletter or updates</li>
            </ul>
            <p>
                This information may include:
            </p>
            <ul>
                <li>Name and email address</li>
                <li>Username and password</li>
                <li>Any other information you choose to provide</li>
            </ul>

            <h3>2.2 Automatically Collected Information</h3>
            <p>
                When you use our Service, we may automatically collect certain information, including:
            </p>
            <ul>
                <li>IP address</li>
                <li>Browser type and version</li>
                <li>Device information</li>
                <li>Usage data (pages visited, time spent, etc.)</li>
                <li>Cookies and similar tracking technologies</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>3. How We Use Your Information</h2>
            <p>
                We use the information we collect to:
            </p>
            <ul>
                <li>Provide, maintain, and improve our Service</li>
                <li>Process your registration and manage your account</li>
                <li>Respond to your inquiries and provide customer support</li>
                <li>Send you administrative information and updates</li>
                <li>Monitor and analyze usage patterns and trends</li>
                <li>Detect, prevent, and address technical issues</li>
                <li>Comply with legal obligations</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>4. Cookies and Tracking Technologies</h2>
            <p>
                We use cookies and similar tracking technologies to track activity on our Service and store certain information. Cookies are files with a small amount of data which may include an anonymous unique identifier.
            </p>
            <p>
                You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent. However, if you do not accept cookies, you may not be able to use some portions of our Service.
            </p>
            <p>
                For more detailed information about our use of cookies, please see our <a href="<?php echo BASE_URL; ?>/cookie-policy" class="text-netflix-red hover:underline">Cookie Policy</a>.
            </p>
        </div>

        <div class="content-section">
            <h2>5. Data Sharing and Disclosure</h2>
            <p>
                We do not sell, trade, or rent your personal information to third parties. We may share your information only in the following circumstances:
            </p>
            <ul>
                <li><strong>Service Providers:</strong> We may share information with third-party service providers who perform services on our behalf, such as hosting, analytics, and customer support</li>
                <li><strong>Legal Requirements:</strong> We may disclose your information if required to do so by law or in response to valid requests by public authorities</li>
                <li><strong>Business Transfers:</strong> In the event of a merger, acquisition, or sale of assets, your information may be transferred</li>
                <li><strong>With Your Consent:</strong> We may share your information with your explicit consent</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>6. Data Security</h2>
            <p>
                We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the Internet or electronic storage is 100% secure, and we cannot guarantee absolute security.
            </p>
        </div>

        <div class="content-section">
            <h2>7. Data Retention</h2>
            <p>
                We retain your personal information only for as long as necessary to fulfill the purposes outlined in this Privacy Policy, unless a longer retention period is required or permitted by law.
            </p>
        </div>

        <div class="content-section">
            <h2>8. Your Rights</h2>
            <p>
                Depending on your location, you may have certain rights regarding your personal information, including:
            </p>
            <ul>
                <li><strong>Access:</strong> The right to access and receive a copy of your personal information</li>
                <li><strong>Rectification:</strong> The right to correct inaccurate or incomplete information</li>
                <li><strong>Erasure:</strong> The right to request deletion of your personal information</li>
                <li><strong>Objection:</strong> The right to object to processing of your personal information</li>
                <li><strong>Data Portability:</strong> The right to receive your personal information in a structured format</li>
            </ul>
            <p>
                To exercise these rights, please contact us through our <a href="<?php echo BASE_URL; ?>/contact" class="text-netflix-red hover:underline">contact page</a>.
            </p>
        </div>

        <div class="content-section">
            <h2>9. Children's Privacy</h2>
            <p>
                Our Service is not intended for children under the age of 13. We do not knowingly collect personal information from children under 13. If you are a parent or guardian and believe your child has provided us with personal information, please contact us immediately.
            </p>
        </div>

        <div class="content-section">
            <h2>10. Third-Party Links</h2>
            <p>
                Our Service may contain links to third-party websites or services that are not owned or controlled by us. We have no control over, and assume no responsibility for, the privacy practices of these third-party sites. We encourage you to review the privacy policies of any third-party sites you visit.
            </p>
        </div>

        <div class="content-section">
            <h2>11. International Data Transfers</h2>
            <p>
                Your information may be transferred to and maintained on computers located outside of your state, province, country, or other governmental jurisdiction where data protection laws may differ. By using our Service, you consent to the transfer of your information to these facilities.
            </p>
        </div>

        <div class="content-section">
            <h2>12. Changes to This Privacy Policy</h2>
            <p>
                We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date. You are advised to review this Privacy Policy periodically for any changes.
            </p>
        </div>

        <div class="highlight-box">
            <p>
                <strong>Your Privacy Matters:</strong> We are committed to protecting your privacy and being transparent about how we collect and use your information. If you have any questions or concerns about this Privacy Policy, please contact us.
            </p>
        </div>

        <div class="content-section">
            <h2>13. Contact Us</h2>
            <p>
                If you have any questions about this Privacy Policy, please contact us through our <a href="<?php echo BASE_URL; ?>/contact" class="text-netflix-red hover:underline">contact page</a>.
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
