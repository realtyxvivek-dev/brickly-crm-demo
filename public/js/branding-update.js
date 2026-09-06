// Branding Update Script - Updates all buttons dynamically when colors change
(function() {
    'use strict';
    
    // Function to update all gradient buttons with current CSS variables
    function updateGradientButtons() {
        const root = document.documentElement;
        const gradientStart = getComputedStyle(root).getPropertyValue('--gradient-start').trim();
        const gradientEnd = getComputedStyle(root).getPropertyValue('--gradient-end').trim();
        const accentColor = getComputedStyle(root).getPropertyValue('--accent-color').trim();
        
        if (!gradientStart || !gradientEnd) return;
        
        // Find all buttons and links with gradient classes
        const gradientButtons = document.querySelectorAll(
            'a[class*="bg-gradient-to-r"], button[class*="bg-gradient-to-r"], ' +
            'a[class*="from-[#"], button[class*="from-[#"]'
        );
        
        gradientButtons.forEach(btn => {
            // Only update if it has hardcoded gradient
            if (btn.className.includes('from-[#063A1C]') || 
                btn.className.includes('from-[#205A44]') ||
                btn.className.includes('bg-gradient-to-r')) {
                btn.style.background = `linear-gradient(135deg, ${gradientStart}, ${gradientEnd})`;
                
                // Update hover state
                btn.addEventListener('mouseenter', function() {
                    this.style.background = `linear-gradient(135deg, ${gradientEnd}, ${accentColor})`;
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.background = `linear-gradient(135deg, ${gradientStart}, ${gradientEnd})`;
                });
            }
        });
    }
    
    // Run on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateGradientButtons);
    } else {
        updateGradientButtons();
    }
    
    // Also run after a short delay to catch dynamically loaded content
    setTimeout(updateGradientButtons, 500);
})();
