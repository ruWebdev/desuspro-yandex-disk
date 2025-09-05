'use strict';

// Add backdrop element
const backdrop = document.createElement('div');
backdrop.className = 'main-backdrop';
document.body.appendChild(backdrop);

// Initialize when DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(tooltipTriggerEl => {
    new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Show/hide sidebar in mobile
  const menuLink = document.getElementById('menuLink');
  if (menuLink) {
    menuLink.addEventListener('click', (e) => {
      e.preventDefault();
      document.body.classList.toggle('sidebar-show');
    });
  }

  // Menu sidebar toggle
  const menuSidebar = document.getElementById('menuSidebar');
  if (menuSidebar) {
    menuSidebar.addEventListener('click', (e) => {
      e.preventDefault();
      
      if (window.matchMedia('(min-width: 992px)').matches) {
        document.body.classList.toggle('sidebar-hide');
      } else {
        document.body.classList.toggle('sidebar-show');
      }
    });
  }

  // Menu sidebar offset toggle
  const menuSidebarOffset = document.getElementById('menuSidebarOffset');
  if (menuSidebarOffset) {
    menuSidebarOffset.addEventListener('click', (e) => {
      e.preventDefault();
      document.body.classList.toggle('sidebar-show');
    });
  }

  // Hide sidebar when clicking on backdrop
  backdrop.addEventListener('click', () => {
    document.body.classList.remove('sidebar-show', 'sideright-show');
  });

  // Sidebar Interaction
  const psSidebar = new PerfectScrollbar('#sidebarMenu', {
    suppressScrollX: true
  });

  // Handle nav-label clicks
  document.querySelectorAll('.sidebar .nav-label').forEach(label => {
    label.addEventListener('click', (e) => {
      e.preventDefault();
      const parent = label.parentElement;
      parent.classList.toggle('show');
      document.querySelector('.sidebar')?.classList.remove('footer-menu-show');
      psSidebar.update();
    });
  });

  // Handle has-sub menu items
  document.querySelectorAll('.sidebar .has-sub').forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const parent = item.parentElement;
      
      // Close other dropdowns
      const siblings = Array.from(parent.parentElement?.children || []);
      siblings.forEach(sibling => {
        if (sibling !== parent) {
          sibling.classList.remove('show');
        }
      });
      
      parent.classList.toggle('show');
      document.querySelector('.sidebar')?.classList.remove('footer-menu-show');
      psSidebar.update();
    });
  });

  // Handle sidebar footer menu
  const sidebarFooterMenu = document.getElementById('sidebarFooterMenu');
  if (sidebarFooterMenu) {
    sidebarFooterMenu.addEventListener('click', (e) => {
      e.preventDefault();
      const sidebar = sidebarFooterMenu.closest('.sidebar');
      if (sidebar) {
        sidebar.classList.toggle('footer-menu-show');
      }
    });
  }

  // Close sidebar footer menu when clicking outside
  document.addEventListener('click', (e) => {
    const sidebar = document.querySelector('.sidebar');
    const sidebarFooter = document.querySelector('.sidebar-footer');
    
    if (sidebarFooter && !sidebarFooter.contains(e.target)) {
      sidebar?.classList.remove('footer-menu-show');
    }
  });

  // Form search focus effects
  document.querySelectorAll('.form-search .form-control').forEach(input => {
    input.addEventListener('focus', () => {
      input.parentElement?.classList.add('onfocus');
    });
    
    input.addEventListener('blur', () => {
      input.parentElement?.classList.remove('onfocus');
    });
  });
});

// Header mobile effect on scroll
function animateHead() {
  const mainMobileHeader = document.querySelector('.main-mobile-header');
  if (!mainMobileHeader) return;
  
  if (window.scrollY > 20) {
    mainMobileHeader.classList.add('scroll');
  } else {
    mainMobileHeader.classList.remove('scroll');
  }
}

// Add scroll event listener
window.addEventListener('scroll', animateHead);

// Handle skin mode
document.addEventListener('DOMContentLoaded', () => {
  // Load skin mode from localStorage
  const skinMode = localStorage.getItem('skin-mode');
  const html = document.documentElement;
  const skinModeLinks = document.querySelectorAll('#skinMode .nav-link');
  
  if (skinMode === 'dark') {
    html.setAttribute('data-skin', 'dark');
    skinModeLinks.forEach(link => {
      if (link.textContent.toLowerCase().includes('dark')) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });
  }

  // Set skin mode
  skinModeLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      
      // Update active state
      skinModeLinks.forEach(l => l.classList.remove('active'));
      link.classList.add('active');

      const mode = link.textContent.trim().toLowerCase();
      const html = document.documentElement;
      
      if (mode === 'dark') {
        html.setAttribute('data-skin', 'dark');
        localStorage.setItem('skin-mode', mode);
      } else {
        html.removeAttribute('data-skin');
        localStorage.removeItem('skin-mode');
      }
    });
  });
});

// Handle sidebar skin
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('.sidebar');
  const sidebarSkinLinks = document.querySelectorAll('#sidebarSkin .nav-link');
  
  // Load saved sidebar skin
  const loadSidebarSkin = () => {
    const sidebarSkin = localStorage.getItem('sidebar-skin');
    if (!sidebar || !sidebarSkin) return;
    
    // Remove all sidebar skin classes and add the selected one
    sidebar.className = 'sidebar';
    sidebar.classList.add(`sidebar-${sidebarSkin}`);

    // Update active state in the UI
    sidebarSkinLinks.forEach((link, index) => {
      if ((sidebarSkin === 'prime' && index === 1) || 
          (sidebarSkin === 'light' && index === 0) ||
          (sidebarSkin === 'dark' && index === 2)) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });
  };

  // Initialize sidebar skin if sidebar exists
  if (sidebar && sidebarSkinLinks.length > 0) {
    loadSidebarSkin();

    // Set up click handlers for sidebar skin selection
    sidebarSkinLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        
        // Update active state
        sidebarSkinLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        
        // Get the selected skin from the link text
        const skin = link.textContent.trim().toLowerCase();
        
        // Save the selected skin
        if (skin === 'light') {
          localStorage.removeItem('sidebar-skin');
        } else {
          localStorage.setItem('sidebar-skin', skin);
        }
        
        // Apply the selected skin
        loadSidebarSkin();
      });
    });
  }
});
