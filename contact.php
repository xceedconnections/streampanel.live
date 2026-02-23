<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();
$site_name = getSetting($conn, 'site_name', 'StreamFlix');

$page_title = "Contact Us | {$site_name}";
$meta_description = "Get in touch with {$site_name}. We're here to help with any questions, concerns, or feedback about our streaming platform.";
$meta_keywords = "contact, support, help, feedback, streaming platform";

$message_sent = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Here you can add code to save the message to database or send email
        // For now, we'll just show a success message
        $message_sent = true;
    }
}

include 'includes/header.php';
?>

<style>
.page-container {
    min-height: 100vh;
    background: #000;
    padding: 4rem 0;
}
.content-wrapper {
    max-width: 800px;
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
.page-description {
    font-size: 1.125rem;
    color: #9ca3af;
    margin-bottom: 3rem;
    line-height: 1.6;
}
.contact-form {
    background: #141414;
    padding: 2rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}
@media (min-width: 768px) {
    .contact-form {
        padding: 3rem;
    }
}
.form-group {
    margin-bottom: 1.5rem;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #fff;
}
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    background: #1a1a1a;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 0.375rem;
    color: #fff;
    font-size: 1rem;
    transition: border-color 0.2s;
}
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #e50914;
}
.form-group textarea {
    min-height: 150px;
    resize: vertical;
}
.submit-btn {
    background: #e50914;
    color: #fff;
    padding: 0.75rem 2rem;
    border: none;
    border-radius: 0.375rem;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.2s;
    width: 100%;
}
@media (min-width: 768px) {
    .submit-btn {
        width: auto;
    }
}
.submit-btn:hover {
    background: #b20710;
}
.alert {
    padding: 1rem;
    border-radius: 0.375rem;
    margin-bottom: 1.5rem;
}
.alert-success {
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.5);
    color: #6ee7b7;
}
.alert-error {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.5);
    color: #fca5a5;
}
.contact-info {
    margin-top: 3rem;
    padding-top: 3rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}
.contact-info h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #fff;
}
.contact-info p {
    color: #9ca3af;
    line-height: 1.8;
    margin-bottom: 0.5rem;
}
</style>

<div class="page-container animate-in fade-in">
    <div class="content-wrapper">
        <h1 class="page-title">Contact Us</h1>
        <p class="page-description">
            Have a question, concern, or feedback? We'd love to hear from you. Fill out the form below and we'll get back to you as soon as possible.
        </p>

        <?php if ($message_sent): ?>
        <div class="alert alert-success">
            <strong>Thank you!</strong> Your message has been sent successfully. We'll get back to you soon.
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="contact-form">
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="subject">Subject *</label>
                <input type="text" id="subject" name="subject" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="message">Message *</label>
                <textarea id="message" name="message" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="submit-btn">Send Message</button>
        </form>

        <div class="contact-info">
            <h3>Other Ways to Reach Us</h3>
            <p>
                If you prefer not to use the contact form, you can also reach out to us through the following:
            </p>
            <p>
                <strong>For General Inquiries:</strong> Use the contact form above for the fastest response.
            </p>
            <p>
                <strong>For Copyright Concerns:</strong> If you are a copyright owner and believe that your content has been indexed inappropriately, please use the contact form and select "Copyright Inquiry" as your subject.
            </p>
            <p>
                <strong>Response Time:</strong> We aim to respond to all inquiries within 48-72 hours during business days.
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
