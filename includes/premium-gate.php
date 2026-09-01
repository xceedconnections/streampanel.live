<?php
/**
 * Premium subscription gate overlay.
 *
 * Expected variables:
 * - $premium_gate_back_url
 * - $premium_gate_back_label
 * - $premium_gate_title (optional)
 * - $premium_gate_message (optional)
 */
$premium_gate_back_url = $premium_gate_back_url ?? url('movies');
$premium_gate_back_label = $premium_gate_back_label ?? 'Back to Movies';
$premium_gate_title = $premium_gate_title ?? 'Premium Content';
$premium_gate_message = $premium_gate_message ?? 'This content is available exclusively for Premium subscribers.';
?>
<style>
.premium-gate-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.92);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
}
.premium-gate-modal {
    background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    padding: 2rem;
    max-width: 480px;
    width: 100%;
    text-align: center;
    color: #fff;
}
.premium-gate-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1.5rem;
    background: rgba(229, 9, 20, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #e50914;
}
.premium-gate-modal h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
}
.premium-gate-modal p {
    color: #9ca3af;
    margin-bottom: 1.5rem;
    line-height: 1.5;
}
.premium-gate-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.premium-gate-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
}
.premium-gate-btn-primary {
    background: #e50914;
    color: #fff;
}
.premium-gate-btn-primary:hover {
    background: #c40812;
}
.premium-gate-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}
.premium-gate-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}
</style>
<div class="premium-gate-overlay" id="premium-gate-modal">
    <div class="premium-gate-modal">
        <div class="premium-gate-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                <path d="M2 17l10 5 10-5"></path>
                <path d="M2 12l10 5 10-5"></path>
            </svg>
        </div>
        <h2><?php echo htmlspecialchars($premium_gate_title); ?></h2>
        <p><?php echo htmlspecialchars($premium_gate_message); ?></p>
        <div class="premium-gate-actions">
            <a href="<?php echo htmlspecialchars(url('profile')); ?>" class="premium-gate-btn premium-gate-btn-primary">
                Subscribe Now
            </a>
            <a href="<?php echo htmlspecialchars($premium_gate_back_url); ?>" class="premium-gate-btn premium-gate-btn-secondary">
                <?php echo htmlspecialchars($premium_gate_back_label); ?>
            </a>
        </div>
    </div>
</div>
