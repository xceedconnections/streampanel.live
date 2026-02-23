<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();
$site_name = getSetting($conn, 'site_name', 'StreamFlix');

$page_title = "Careers | {$site_name}";
$meta_description = "Join the {$site_name} team. Explore career opportunities and help us build the future of streaming.";
$meta_keywords = "careers, jobs, employment, streaming platform, tech jobs";

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
.job-card {
    background: #141414;
    padding: 2rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 1.5rem;
}
.job-card h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #fff;
}
.job-card .job-meta {
    color: #9ca3af;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}
.job-card .job-description {
    color: #d1d5db;
    line-height: 1.8;
    margin-bottom: 1rem;
}
.job-card .job-requirements {
    margin-top: 1rem;
}
.job-card .job-requirements h4 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #fff;
}
.cta-box {
    background: rgba(229, 9, 20, 0.1);
    border-left: 4px solid #e50914;
    padding: 1.5rem;
    margin: 2rem 0;
    border-radius: 0.5rem;
}
.cta-box p {
    margin: 0;
    color: #fca5a5;
}
.cta-link {
    display: inline-block;
    margin-top: 1rem;
    color: #e50914;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;
}
.cta-link:hover {
    color: #b20710;
    text-decoration: underline;
}
</style>

<div class="page-container animate-in fade-in">
    <div class="content-wrapper">
        <h1 class="page-title">Careers</h1>
        
        <div class="content-section">
            <h2>Join Our Team</h2>
            <p>
                At <?php echo htmlspecialchars($site_name); ?>, we're building the future of streaming content discovery. We're always looking for talented individuals who are passionate about technology, user experience, and creating innovative solutions.
            </p>
            <p>
                While we may not have open positions at all times, we're always interested in connecting with exceptional people who share our vision. If you're interested in joining our team, please check back regularly for new opportunities or reach out to us through our <a href="<?php echo BASE_URL; ?>/contact" class="text-netflix-red hover:underline">contact page</a>.
            </p>
        </div>

        <div class="content-section">
            <h2>Current Openings</h2>
            <p>
                We currently don't have any open positions, but we're always growing. Check back soon for new opportunities!
            </p>
            <p>
                If you're interested in working with us and don't see a position that matches your skills, feel free to send us your resume and a cover letter explaining why you'd like to join our team. We keep all applications on file and will reach out if a suitable position becomes available.
            </p>
        </div>

        <div class="content-section">
            <h2>What We Look For</h2>
            <p>
                We value team members who are:
            </p>
            <ul>
                <li><strong>Passionate:</strong> Genuinely excited about streaming technology and user experience</li>
                <li><strong>Innovative:</strong> Always thinking of new ways to improve our platform</li>
                <li><strong>Collaborative:</strong> Great at working with others and contributing to team success</li>
                <li><strong>Adaptable:</strong> Comfortable working in a fast-paced, evolving environment</li>
                <li><strong>Detail-oriented:</strong> Committed to delivering high-quality work</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>Benefits & Culture</h2>
            <p>
                While specific benefits may vary by position and location, we're committed to creating a positive work environment where our team can thrive. We believe in:
            </p>
            <ul>
                <li>Work-life balance</li>
                <li>Continuous learning and professional development</li>
                <li>Open communication and transparency</li>
                <li>Innovation and creative problem-solving</li>
                <li>Diversity and inclusion</li>
            </ul>
        </div>

        <div class="cta-box">
            <p>
                <strong>Interested in joining us?</strong> Even if we don't have an open position that matches your skills right now, we'd love to hear from you. Send us your resume and a brief note about why you're interested in <?php echo htmlspecialchars($site_name); ?>.
            </p>
            <a href="<?php echo BASE_URL; ?>/contact" class="cta-link">Get in Touch →</a>
        </div>

        <div class="content-section">
            <h2>Equal Opportunity</h2>
            <p>
                <?php echo htmlspecialchars($site_name); ?> is an equal opportunity employer. We celebrate diversity and are committed to creating an inclusive environment for all employees. We do not discriminate on the basis of race, color, religion, gender, gender identity or expression, sexual orientation, national origin, genetics, disability, age, or veteran status.
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
