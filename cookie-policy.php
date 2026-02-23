<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();
$site_name = getSetting($conn, 'site_name', 'StreamFlix');

$page_title = "Cookie Policy | {$site_name}";
$meta_description = "Learn about how {$site_name} uses cookies and similar technologies to enhance your browsing experience.";
$meta_keywords = "cookie policy, cookies, tracking, web technologies";

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
.cookie-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.5rem 0;
    background: #141414;
    border-radius: 0.5rem;
    overflow: hidden;
}
.cookie-table th,
.cookie-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.cookie-table th {
    background: #1a1a1a;
    color: #fff;
    font-weight: 600;
}
.cookie-table td {
    color: #d1d5db;
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
        <h1 class="page-title">Cookie Policy</h1>
        <p class="last-updated">Last Updated: <?php echo date('F j, Y'); ?></p>

        <div class="content-section">
            <h2>1. What Are Cookies?</h2>
            <p>
                Cookies are small text files that are placed on your computer or mobile device when you visit a website. They are widely used to make websites work more efficiently and provide information to the website owners.
            </p>
            <p>
                Cookies allow a website to recognize your device and store some information about your preferences or past actions. This helps improve your browsing experience by remembering your preferences and settings.
            </p>
        </div>

        <div class="content-section">
            <h2>2. How We Use Cookies</h2>
            <p>
                <?php echo htmlspecialchars($site_name); ?> uses cookies and similar tracking technologies to:
            </p>
            <ul>
                <li>Keep you signed in to your account</li>
                <li>Remember your preferences and settings</li>
                <li>Understand how you use our Service</li>
                <li>Improve and optimize our Service</li>
                <li>Provide personalized content and features</li>
                <li>Analyze traffic and usage patterns</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>3. Types of Cookies We Use</h2>
            
            <h3>3.1 Essential Cookies</h3>
            <p>
                These cookies are necessary for the Service to function properly. They enable core functionality such as security, network management, and accessibility. You cannot opt-out of these cookies as they are essential for the Service to work.
            </p>
            <p>
                Examples include:
            </p>
            <ul>
                <li>Session cookies that keep you logged in</li>
                <li>Security cookies that protect against fraud</li>
                <li>Cookies that remember your language preferences</li>
            </ul>

            <h3>3.2 Functional Cookies</h3>
            <p>
                These cookies allow the Service to remember choices you make (such as your username, language, or region) and provide enhanced, personalized features.
            </p>
            <p>
                Examples include:
            </p>
            <ul>
                <li>Cookies that remember your viewing preferences</li>
                <li>Cookies that store your favorite channels or content</li>
                <li>Cookies that remember your display settings</li>
            </ul>

            <h3>3.3 Analytics Cookies</h3>
            <p>
                These cookies help us understand how visitors interact with our Service by collecting and reporting information anonymously. This helps us improve the Service and user experience.
            </p>
            <p>
                Examples include:
            </p>
            <ul>
                <li>Cookies that track which pages are most popular</li>
                <li>Cookies that measure how long users spend on pages</li>
                <li>Cookies that identify errors and technical issues</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>4. Third-Party Cookies</h2>
            <p>
                In addition to our own cookies, we may also use various third-party cookies to report usage statistics of the Service, deliver advertisements, and so on. These third-party cookies are subject to the respective privacy policies of these third parties.
            </p>
            <p>
                Common third-party services that may set cookies include:
            </p>
            <ul>
                <li>Analytics services (e.g., Google Analytics)</li>
                <li>Content delivery networks</li>
                <li>Social media platforms (if integrated)</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>5. Cookie Duration</h2>
            <p>
                Cookies can be either "session" cookies or "persistent" cookies:
            </p>
            <ul>
                <li><strong>Session Cookies:</strong> These are temporary cookies that expire when you close your browser. They are used to maintain your session while you browse our Service.</li>
                <li><strong>Persistent Cookies:</strong> These cookies remain on your device for a set period or until you delete them. They are used to remember your preferences and settings across multiple visits.</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>6. Managing Cookies</h2>
            <p>
                You have the right to decide whether to accept or reject cookies. You can exercise your cookie rights by setting your browser preferences. Most browsers allow you to:
            </p>
            <ul>
                <li>See what cookies you have and delete them individually</li>
                <li>Block third-party cookies</li>
                <li>Block cookies from particular sites</li>
                <li>Block all cookies from being set</li>
                <li>Delete all cookies when you close your browser</li>
            </ul>
            <p>
                However, please note that if you choose to block or delete cookies, some features of our Service may not function properly or may not be available to you.
            </p>
        </div>

        <div class="content-section">
            <h2>7. Browser-Specific Instructions</h2>
            <p>
                To manage cookies in your browser, please refer to the following links:
            </p>
            <ul>
                <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener" class="text-netflix-red hover:underline">Google Chrome</a></li>
                <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" rel="noopener" class="text-netflix-red hover:underline">Mozilla Firefox</a></li>
                <li><a href="https://support.apple.com/guide/safari/manage-cookies-and-website-data-sfri11471/mac" target="_blank" rel="noopener" class="text-netflix-red hover:underline">Safari</a></li>
                <li><a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener" class="text-netflix-red hover:underline">Microsoft Edge</a></li>
            </ul>
        </div>

        <div class="content-section">
            <h2>8. Do Not Track Signals</h2>
            <p>
                Some browsers include a "Do Not Track" (DNT) feature that signals to websites you visit that you do not want to have your online activity tracked. Currently, there is no standard for how DNT signals should be interpreted. As a result, our Service does not currently respond to DNT browser signals or mechanisms.
            </p>
        </div>

        <div class="content-section">
            <h2>9. Updates to This Cookie Policy</h2>
            <p>
                We may update this Cookie Policy from time to time to reflect changes in our practices or for other operational, legal, or regulatory reasons. We will notify you of any material changes by updating the "Last Updated" date at the top of this page.
            </p>
        </div>

        <div class="highlight-box">
            <p>
                <strong>Your Control:</strong> You have control over cookies. You can set your browser to refuse cookies, but this may limit your ability to use some features of our Service.
            </p>
        </div>

        <div class="content-section">
            <h2>10. Contact Us</h2>
            <p>
                If you have any questions about our use of cookies or this Cookie Policy, please contact us through our <a href="<?php echo BASE_URL; ?>/contact" class="text-netflix-red hover:underline">contact page</a>.
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
