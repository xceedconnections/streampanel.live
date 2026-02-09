<?php
/**
 * Public Countdown Display Page
 * Shows countdown timer with no header/footer - just title, description, and timer
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';

$conn = getDBConnection();

// Get slug from URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    // Try to get from PATH_INFO or REQUEST_URI
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/countdown/([a-z0-9-]+)#', $request_uri, $matches)) {
        $slug = $matches[1];
    }
}

if (empty($slug)) {
    http_response_code(404);
    die('Countdown not found');
}

// Fetch countdown from database
$stmt = $conn->prepare("SELECT * FROM countdowns WHERE slug = ? AND is_active = 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$countdown = $stmt->get_result()->fetch_assoc();

if (!$countdown) {
    http_response_code(404);
    die('Countdown not found');
}

// Set timezone to Pakistan Standard Time (PKT)
date_default_timezone_set('Asia/Karachi');

// Parse target datetime
$target_datetime = new DateTime($countdown['target_datetime'], new DateTimeZone('Asia/Karachi'));
$target_timestamp = $target_datetime->getTimestamp();

// Get current time in PKT
$current_datetime = new DateTime('now', new DateTimeZone('Asia/Karachi'));
$current_timestamp = $current_datetime->getTimestamp();

// Calculate difference
$time_diff = $target_timestamp - $current_timestamp;

// Format target date for display
$target_formatted = $target_datetime->format('F j, Y \a\t g:i A') . ' PKT';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($countdown['title']); ?> - Countdown</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #000;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            text-align: center;
        }
        
        .countdown-container {
            max-width: 800px;
            width: 100%;
        }
        
        .countdown-title {
            font-size: 3rem;
            font-weight: 700;
            color: #e50914;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        @media (max-width: 768px) {
            .countdown-title {
                font-size: 2rem;
            }
        }
        
        .countdown-description {
            font-size: 1.25rem;
            color: #b3b3b3;
            margin-bottom: 3rem;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .countdown-description {
                font-size: 1rem;
                margin-bottom: 2rem;
            }
        }
        
        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .countdown-timer {
                gap: 1rem;
            }
        }
        
        .timer-unit {
            background: linear-gradient(135deg, #e50914 0%, #b20710 100%);
            border-radius: 12px;
            padding: 2rem 1.5rem;
            min-width: 120px;
            box-shadow: 0 8px 24px rgba(229, 9, 20, 0.4);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .timer-unit:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(229, 9, 20, 0.6);
        }
        
        @media (max-width: 768px) {
            .timer-unit {
                min-width: 80px;
                padding: 1.5rem 1rem;
            }
        }
        
        .timer-value {
            font-size: 4rem;
            font-weight: 700;
            color: #fff;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .timer-value {
                font-size: 2.5rem;
            }
        }
        
        .timer-label {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .target-date {
            font-size: 1.125rem;
            color: #808080;
            margin-top: 2rem;
        }
        
        .expired-message {
            font-size: 2rem;
            color: #e50914;
            font-weight: 700;
            margin-top: 2rem;
        }
        
        .loading {
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="countdown-container">
        <h1 class="countdown-title"><?php echo htmlspecialchars($countdown['title']); ?></h1>
        
        <?php if (!empty($countdown['description'])): ?>
        <p class="countdown-description"><?php echo nl2br(htmlspecialchars($countdown['description'])); ?></p>
        <?php endif; ?>
        
        <div class="countdown-timer" id="countdownTimer">
            <div class="timer-unit">
                <div class="timer-value" id="days">00</div>
                <div class="timer-label">Days</div>
            </div>
            <div class="timer-unit">
                <div class="timer-value" id="hours">00</div>
                <div class="timer-label">Hours</div>
            </div>
            <div class="timer-unit">
                <div class="timer-value" id="minutes">00</div>
                <div class="timer-label">Minutes</div>
            </div>
            <div class="timer-unit">
                <div class="timer-value" id="seconds">00</div>
                <div class="timer-label">Seconds</div>
            </div>
        </div>
        
        <div class="target-date" id="targetDate">
            Target: <?php echo $target_formatted; ?>
        </div>
        
        <div class="expired-message" id="expiredMessage" style="display: none;">
            Countdown has ended!
        </div>
    </div>
    
    <script>
        // Target timestamp in milliseconds (PKT timezone)
        const targetTimestamp = <?php echo $target_timestamp * 1000; ?>;
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = targetTimestamp - now;
            
            if (distance < 0) {
                // Countdown has ended
                document.getElementById('countdownTimer').style.display = 'none';
                document.getElementById('targetDate').style.display = 'none';
                document.getElementById('expiredMessage').style.display = 'block';
                return;
            }
            
            // Calculate time units
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            // Update display
            document.getElementById('days').textContent = String(days).padStart(2, '0');
            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
        }
        
        // Update immediately
        updateCountdown();
        
        // Update every second
        setInterval(updateCountdown, 1000);
    </script>
</body>
</html>
