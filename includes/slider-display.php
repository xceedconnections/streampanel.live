<?php
/**
 * Slider Display Component
 * Displays sliders with auto-rotate and navigation arrows.
 * Slider content is ONLY from Admin > Sliders (slider_slides). Nothing is auto-added from featured or show_in_slider.
 */

// Get sliders for the current page (only from Admin > Sliders)
$page_sliders = getSlidersForPage($conn, $page_type);

// On home page (index), do not show TV show slides in the slider - only movies, live_tv, external
if ($page_type === 'home') {
    foreach ($page_sliders as &$s) {
        $s['slides'] = array_filter($s['slides'] ?? [], function ($slide) {
            return ($slide['link_type'] ?? '') !== 'tv_show';
        });
        $s['slides'] = array_values($s['slides']);
    }
    unset($s);
}

// Only show slider when at least one slider has at least one slide added in admin
$page_sliders = array_filter($page_sliders, function ($s) {
    return !empty($s['slides']);
});

if (empty($page_sliders)) {
    return; // No sliders with slides to display
}

// Let the parent page know that sliders are present (used for layout adjustments)
$GLOBALS['page_has_sliders'] = true;
?>

<style>
/* Slider Styles */
.slider-container {
    position: relative;
    width: 100%;
    margin-bottom: 3rem;
    overflow: hidden;
}

.slider-wrapper {
    position: relative;
    width: 100%;
    overflow: hidden;
    background: #000;
}

/* Responsive height based on aspect ratio - maintain 16:9 ratio */
.slider-wrapper {
    aspect-ratio: 16 / 9;
    max-height: 80vh;
}

@media (max-width: 640px) {
    .slider-wrapper {
        aspect-ratio: 16 / 10;
        max-height: 50vh;
    }
}

@media (min-width: 768px) {
    .slider-wrapper {
        aspect-ratio: 16 / 9;
        max-height: 70vh;
    }
}

@media (min-width: 1024px) {
    .slider-wrapper {
        aspect-ratio: 16 / 9;
        max-height: 80vh;
    }
}

.slider-slides {
    display: flex;
    transition: transform 0.6s ease-in-out;
    height: 100%;
    will-change: transform;
}

.slider-slide {
    min-width: 100%;
    height: 100%;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #000;
}

.slider-slide img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    display: block;
}

.slider-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8), rgba(0,0,0,0.3), transparent);
    z-index: 1;
}

.slider-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 2rem;
    z-index: 2;
    max-width: 800px;
}

@media (min-width: 768px) {
    .slider-content {
        padding: 3rem;
    }
}

.slider-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #fff;
}

@media (min-width: 768px) {
    .slider-title {
        font-size: 2rem;
    }
}

.slider-description {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 1rem;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@media (min-width: 768px) {
    .slider-description {
        font-size: 1rem;
        -webkit-line-clamp: 3;
    }
}

.slider-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    border: none;
    color: #fff;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 1.25rem;
}

.slider-nav-btn:hover {
    background: rgba(0, 0, 0, 0.8);
    transform: translateY(-50%) scale(1.1);
}

.slider-nav-btn.prev {
    left: 1rem;
}

.slider-nav-btn.next {
    right: 1rem;
}

.slider-dots {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 0.5rem;
    z-index: 10;
}

.slider-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0;
}

.slider-dot.active {
    background: #e50914;
    width: 30px;
    border-radius: 5px;
}

.slider-dot:hover {
    background: rgba(255, 255, 255, 0.8);
}
</style>

<?php foreach ($page_sliders as $slider): ?>
<?php if (empty($slider['slides'])) continue; ?>
<div class="slider-container" data-slider-id="<?php echo $slider['id']; ?>" 
     data-auto-rotate="<?php echo $slider['auto_rotate'] ? 'true' : 'false'; ?>"
     data-rotate-interval="<?php echo $slider['rotate_interval'] ?? 5000; ?>">
    <div class="slider-wrapper">
        <div class="slider-slides" id="slider-<?php echo $slider['id']; ?>">
            <?php foreach ($slider['slides'] as $index => $slide): ?>
            <div class="slider-slide" data-slide-index="<?php echo $index; ?>">
                <a href="<?php echo getSlideLink($slide, $conn); ?>" style="display: block; width: 100%; height: 100%;">
                    <img src="<?php echo htmlspecialchars(assetUrl($slide['image_url'])); ?>" 
                         alt="<?php echo htmlspecialchars($slide['title'] ?? 'Slide'); ?>"
                         onerror="this.src='<?php echo url('assets/placeholder.jpg'); ?>'">
                    <div class="slider-overlay"></div>
                    <?php if ($slide['title'] || $slide['description']): ?>
                    <div class="slider-content">
                        <?php if ($slide['title']): ?>
                        <h3 class="slider-title"><?php echo htmlspecialchars($slide['title']); ?></h3>
                        <?php endif; ?>
                        <?php if ($slide['description']): ?>
                        <p class="slider-description"><?php echo htmlspecialchars($slide['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($slider['slides']) > 1): ?>
        <button class="slider-nav-btn prev" onclick="slideSlider(<?php echo $slider['id']; ?>, 'prev')" aria-label="Previous slide">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="slider-nav-btn next" onclick="slideSlider(<?php echo $slider['id']; ?>, 'next')" aria-label="Next slide">
            <i class="fas fa-chevron-right"></i>
        </button>
        
        <div class="slider-dots">
            <?php foreach ($slider['slides'] as $index => $slide): ?>
            <button class="slider-dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                    onclick="goToSlide(<?php echo $slider['id']; ?>, <?php echo $index; ?>)" 
                    aria-label="Go to slide <?php echo $index + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<script>
// Slider functionality
const sliderIntervals = {};

function initSlider(sliderId, autoRotate, interval) {
    const slider = document.querySelector(`[data-slider-id="${sliderId}"]`);
    if (!slider) return;
    
    const slidesContainer = slider.querySelector('.slider-slides');
    const slides = slider.querySelectorAll('.slider-slide');
    const dots = slider.querySelectorAll('.slider-dot');
    
    if (slides.length <= 1) return;
    
    let currentIndex = 0;
    
    function updateSlider() {
        slidesContainer.style.transform = `translateX(-${currentIndex * 100}%)`;
        
        // Update dots
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentIndex);
        });
    }
    
    function nextSlide() {
        currentIndex = (currentIndex + 1) % slides.length;
        updateSlider();
    }
    
    function prevSlide() {
        currentIndex = (currentIndex - 1 + slides.length) % slides.length;
        updateSlider();
    }
    
    function goToSlide(index) {
        currentIndex = index;
        updateSlider();
        // Reset auto-rotate timer
        if (autoRotate) {
            clearInterval(sliderIntervals[sliderId]);
            sliderIntervals[sliderId] = setInterval(nextSlide, interval);
        }
    }
    
    // Store functions globally for button clicks
    window[`slideSlider_${sliderId}`] = {
        next: nextSlide,
        prev: prevSlide,
        goTo: goToSlide,
        currentIndex: () => currentIndex
    };
    
    // Auto-rotate
    if (autoRotate) {
        sliderIntervals[sliderId] = setInterval(nextSlide, interval);
        
        // Pause on hover
        slider.addEventListener('mouseenter', () => {
            clearInterval(sliderIntervals[sliderId]);
        });
        
        slider.addEventListener('mouseleave', () => {
            sliderIntervals[sliderId] = setInterval(nextSlide, interval);
        });
    }
    
    // Touch/swipe support
    let touchStartX = 0;
    let touchEndX = 0;
    
    slidesContainer.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    slidesContainer.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });
    
    function handleSwipe() {
        if (touchEndX < touchStartX - 50) {
            nextSlide();
        }
        if (touchEndX > touchStartX + 50) {
            prevSlide();
        }
    }
}

function slideSlider(sliderId, direction) {
    const sliderFunc = window[`slideSlider_${sliderId}`];
    if (sliderFunc) {
        if (direction === 'next') {
            sliderFunc.next();
        } else {
            sliderFunc.prev();
        }
    }
}

function goToSlide(sliderId, index) {
    const sliderFunc = window[`slideSlider_${sliderId}`];
    if (sliderFunc) {
        sliderFunc.goTo(index);
    }
}

// Initialize all sliders on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($page_sliders as $slider): ?>
    <?php if (count($slider['slides']) > 1): ?>
    initSlider(
        <?php echo $slider['id']; ?>, 
        <?php echo $slider['auto_rotate'] ? 'true' : 'false'; ?>, 
        <?php echo $slider['rotate_interval'] ?? 5000; ?>
    );
    <?php endif; ?>
    <?php endforeach; ?>
});
</script>
