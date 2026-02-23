<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();
$site_name = getSetting($conn, 'site_name', 'StreamFlix');

$page_title = "About Us | {$site_name}";
$meta_description = "Learn about {$site_name} - Your ultimate streaming destination. We provide access to publicly available IPTV channels and streaming content.";
$meta_keywords = "about us, streaming platform, IPTV, online streaming";

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
    margin-bottom: 2rem;
    color: #fff;
}
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
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
.content-section p {
    font-size: 1rem;
    line-height: 1.8;
    color: #d1d5db;
    margin-bottom: 1rem;
}
.content-section ul {
    list-style: disc;
    margin-left: 1.5rem;
    color: #d1d5db;
    line-height: 1.8;
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
        <h1 class="page-title">About Us</h1>
        
        <div class="content-section">
            <h2>Welcome to <?php echo htmlspecialchars($site_name); ?></h2>
            <p>
                <?php echo htmlspecialchars($site_name); ?> is your ultimate streaming destination, providing access to a wide variety of publicly available IPTV channels and streaming content. We are committed to offering users a convenient platform to discover and access streaming content from various sources.
            </p>
        </div>

        <div class="content-section">
            <h2>Our Mission</h2>
            <p>
                Our mission is to provide users with easy access to publicly available streaming content. We aim to create a user-friendly platform that aggregates and indexes publicly available IPTV channels and streaming sources, making it easier for users to discover and enjoy content from around the world.
            </p>
        </div>

        <div class="content-section">
            <h2>What We Do</h2>
            <p>
                <?php echo htmlspecialchars($site_name); ?> operates as an indexing platform for publicly available IPTV channels and streaming content. We:
            </p>
            <ul>
                <li>Index and organize publicly available IPTV channels from open sources</li>
                <li>Provide a user-friendly interface for discovering streaming content</li>
                <li>Aggregate content from publicly available sources, including those originally provided on GitHub</li>
                <li>Offer a convenient platform for users to access streaming channels</li>
            </ul>
        </div>

        <div class="highlight-box">
            <p>
                <strong>Important Notice:</strong> <?php echo htmlspecialchars($site_name); ?> does not broadcast, host, or store any streaming content. We do not accept any copyright or legal responsibility for the content accessed through our platform. We are purely an indexing service for publicly available IPTV channels.
            </p>
        </div>

        <div class="content-section">
            <h2>Content Sources</h2>
            <p>
                All content indexed on our platform comes from publicly available sources, including IPTV channel lists that are originally provided on GitHub and other open-source platforms. We do not create, modify, or host any of the content ourselves. We simply provide an organized interface to access these publicly available resources.
            </p>
        </div>

        <div class="content-section">
            <h2>Copyright & Intellectual Property</h2>
            <p>
                All logos, channel names, and brand identities displayed on our platform are the property of their respective owners. We do not claim ownership of any copyrighted material. If you are a copyright owner and believe that your content has been indexed inappropriately, please contact us through our contact page.
            </p>
        </div>

        <div class="content-section">
            <h2>Our Commitment</h2>
            <p>
                We are committed to:
            </p>
            <ul>
                <li>Providing a transparent and user-friendly platform</li>
                <li>Respecting intellectual property rights</li>
                <li>Operating within the bounds of indexing publicly available content</li>
                <li>Maintaining user privacy and data security</li>
                <li>Continuously improving our platform's functionality and user experience</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>Contact Us</h2>
            <p>
                If you have any questions, concerns, or feedback about our platform, please don't hesitate to <a href="<?php echo BASE_URL; ?>/contact" class="text-netflix-red hover:underline">contact us</a>. We value your input and are always looking to improve our services.
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
