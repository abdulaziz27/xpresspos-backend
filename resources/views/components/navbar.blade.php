<!-- Modern Responsive Navbar -->
<nav class="fixed top-0 w-full z-[100] backdrop-blur-md bg-gray-900/80 dark:bg-gray-900/50 border-b border-gray-700/30 dark:border-gray-700/30 transition-all duration-300 ease-in-out" style="background-color: rgba(17, 24, 39, 0.8) !important; backdrop-filter: blur(12px) !important;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
                <a href="{{ url('/') }}" class="flex items-center space-x-2 group transition-all duration-300 ease-in-out">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center shadow-lg group-hover:shadow-xl group-hover:scale-105 transition-all duration-300">
                        <span class="text-white font-bold text-sm">X</span>
                    </div>
                    <span class="text-xl font-bold bg-gradient-to-r from-gray-900 to-gray-700 dark:from-white dark:to-gray-300 bg-clip-text text-transparent group-hover:from-blue-600 group-hover:to-blue-500 transition-all duration-300">
                        XpressPOS
                    </span>
                </a>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:block">
                <div class="ml-10 flex items-baseline space-x-8">
                    <a href="#hero" 
                       class="nav-link text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 ease-in-out hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:scale-105">
                        Beranda
                    </a>
                    <a href="#features" 
                       class="nav-link text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 ease-in-out hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:scale-105">
                        Fitur
                    </a>
                    <a href="#pricing" 
                       class="nav-link text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 ease-in-out hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:scale-105">
                        Harga
                    </a>
                    <a href="{{ route('login') }}" 
                       class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Login
                    </a>
                </div>
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden">
                <button type="button" 
                        id="mobile-menu-button"
                        class="inline-flex items-center justify-center p-2 rounded-lg text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 transition-all duration-300 ease-in-out"
                        aria-controls="mobile-menu" 
                        aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <!-- Hamburger icon -->
                    <svg id="hamburger-icon" class="block h-6 w-6 transition-transform duration-300 ease-in-out" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <!-- Close icon -->
                    <svg id="close-icon" class="hidden h-6 w-6 transition-transform duration-300 ease-in-out" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="md:hidden hidden">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-gray-900/95 dark:bg-gray-900/90 backdrop-blur-md border-t border-gray-700/30 dark:border-gray-700/30">
            <a href="#hero" 
               class="mobile-nav-link block px-3 py-2 rounded-lg text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all duration-300 ease-in-out">
                Beranda
            </a>
            <a href="#features" 
               class="mobile-nav-link block px-3 py-2 rounded-lg text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all duration-300 ease-in-out">
                Fitur
            </a>
            <a href="#pricing" 
               class="mobile-nav-link block px-3 py-2 rounded-lg text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all duration-300 ease-in-out">
                Harga
            </a>
            <a href="{{ route('login') }}" 
               class="block w-full text-center bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-4 py-2 rounded-lg text-base font-medium transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-lg mt-4">
                Login
            </a>
        </div>
    </div>
</nav>

<!-- JavaScript for mobile menu toggle and smooth scrolling -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const hamburgerIcon = document.getElementById('hamburger-icon');
    const closeIcon = document.getElementById('close-icon');
    
    // Toggle mobile menu
    mobileMenuButton.addEventListener('click', function() {
        const isExpanded = mobileMenuButton.getAttribute('aria-expanded') === 'true';
        
        mobileMenuButton.setAttribute('aria-expanded', !isExpanded);
        
        if (isExpanded) {
            // Close menu
            mobileMenu.classList.add('hidden');
            hamburgerIcon.classList.remove('hidden');
            closeIcon.classList.add('hidden');
            hamburgerIcon.style.transform = 'rotate(0deg)';
        } else {
            // Open menu
            mobileMenu.classList.remove('hidden');
            hamburgerIcon.classList.add('hidden');
            closeIcon.classList.remove('hidden');
            closeIcon.style.transform = 'rotate(180deg)';
        }
    });
    
    // Close mobile menu when clicking on nav links
    const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');
    mobileNavLinks.forEach(link => {
        link.addEventListener('click', function() {
            mobileMenu.classList.add('hidden');
            hamburgerIcon.classList.remove('hidden');
            closeIcon.classList.add('hidden');
            mobileMenuButton.setAttribute('aria-expanded', 'false');
        });
    });
    
    // Smooth scrolling for anchor links
    const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    const offsetTop = target.offsetTop - 80; // Account for fixed navbar height
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
    
    // Add scroll effect to navbar
    let lastScrollTop = 0;
    const navbar = document.querySelector('nav');
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Add/remove shadow based on scroll position
        if (scrollTop > 10) {
            navbar.classList.add('shadow-lg', 'shadow-black/5');
            navbar.classList.remove('shadow-none');
        } else {
            navbar.classList.remove('shadow-lg', 'shadow-black/5');
            navbar.classList.add('shadow-none');
        }
        
        lastScrollTop = scrollTop;
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const isClickInsideNav = navbar.contains(event.target);
        const isMenuOpen = !mobileMenu.classList.contains('hidden');
        
        if (!isClickInsideNav && isMenuOpen) {
            mobileMenu.classList.add('hidden');
            hamburgerIcon.classList.remove('hidden');
            closeIcon.classList.add('hidden');
            mobileMenuButton.setAttribute('aria-expanded', 'false');
        }
    });
});
</script>

<style>
/* Additional custom styles for enhanced animations */
.nav-link {
    position: relative;
    overflow: hidden;
}

.nav-link::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    transition: all 0.3s ease-in-out;
    transform: translateX(-50%);
}

.nav-link:hover::before {
    width: 80%;
}

/* Mobile menu animation */
#mobile-menu {
    animation: slideDown 0.3s ease-in-out;
}

#mobile-menu.hidden {
    animation: slideUp 0.3s ease-in-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-10px);
    }
}

/* Backdrop blur fallback for older browsers */
@supports not (backdrop-filter: blur(12px)) {
    nav {
        background-color: rgba(17, 24, 39, 0.95);
    }
    
    .dark nav {
        background-color: rgba(17, 24, 39, 0.95);
    }
}
</style>