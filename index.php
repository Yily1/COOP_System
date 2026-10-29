<?php
// Public landing page. Standalone — does NOT use renderHeader/renderFooter.
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>San Jose Farmers Multipurpose Cooperative (SJFMC)</title>
    <meta name="description" content="San Jose Farmers Multipurpose Cooperative (SJFMC) — supporting members through quality services, resources, and opportunities.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/landing.css?v=<?php echo filemtime(__DIR__ . '/assets/css/landing.css'); ?>">
</head>
<body>

<!-- ============ NAV ============ -->
<header class="site-header" id="top">
    <nav class="nav-container" aria-label="Main navigation">
        <a href="#hero" class="brand" aria-label="SJFMC home">
            <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="SJFMC logo" class="brand-logo">
            <span class="brand-name">SJFMC</span>
        </a>

        <button class="nav-toggle" id="navToggle"
                aria-label="Open menu" aria-expanded="false" aria-controls="navMenu">
            <span></span><span></span><span></span>
        </button>

        <ul class="nav-menu" id="navMenu">
            <li><a href="#hero">Home</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#about">About Us</a></li>
            <li><a href="#contact">Contact Us</a></li>
            <li><a href="<?php echo BASE_URL; ?>/login.php" class="nav-login">Login</a></li>
        </ul>
    </nav>
</header>

<!-- ============ HERO ============ -->
<section class="hero" id="hero">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <p class="hero-eyebrow">WELCOME TO SJFMC</p>
        <h1>San Jose Farmers<br>Multipurpose Cooperative</h1>
        <p class="hero-text">
           To support our members, we offer quality services, resources, and opportunities for a stronger and more sustainable future. Want to be part of our growing cooperative? Contact us to learn how you can become a member.
        </p>
        <div class="hero-actions">
            <a href="#services" class="btn btn-primary">Explore Our Services</a>
            <a href="#about" class="btn btn-outline">About Us</a>
        </div>
    </div>
</section>

<!-- ============ SERVICES & RESOURCES ============ -->
<section class="section" id="services">
    <div class="container">
        
        <!-- Intro Title -->
        <div class="section-head">
            <h2>We Offer Services and Agricultural Resources</h2>
            <p>The cooperative provides services and agricultural resources to support our members and help our community grow.</p>
        </div>

        <!-- BOX 1: OUR SERVICES -->
        <div class="services-section">
            <div class="services-title-wrap">
                <h3 class="front-title">Our Services</h3>
            </div>
            <div class="services-grid services-grid--three">
                <article class="service-card">
                    <div class="service-icon" aria-hidden="true">🚜</div>
                    <h4>Equipment Rental</h4>
                    <p>Rental of agricultural equipment and post-harvest facilities for member farming operations.</p>
                </article>
                <article class="service-card">
                    <div class="service-icon" aria-hidden="true">🌱</div>
                    <h4>Product Avail &amp; Request</h4>
                    <p>Agricultural products such as seeds, fertilizer, and other farm inputs available to members.</p>
                </article>
                <article class="service-card">
                    <div class="service-icon" aria-hidden="true">💰</div>
                    <h4>Financial Loans</h4>
                    <p>Loan services and financial assistance for qualified members of the cooperative.</p>
                </article>
            </div>
        </div>

        <!-- BOX 2: OUR AGRICULTURAL RESOURCES (Seeds and Fertilizer) -->
        <div class="services-section">
            <div class="services-title-wrap">
                <h3 class="front-title">Our Agricultural Resources</h3>
            </div>
            <div class="services-grid services-grid--two">
                <article class="service-card">
                    <div class="service-icon" aria-hidden="true">🌾</div>
                    <h4>Seeds</h4>
                    <p>Quality seeds distributed to eligible cooperative members for planting.</p>
                </article>
                <article class="service-card">
                    <div class="service-icon" aria-hidden="true">🧪</div>
                    <h4>Fertilizer</h4>
                    <p>Fertilizer distributed to eligible members to support healthy crop growth.</p>
                </article>
            </div>
        </div>

    </div>
</section>

<!-- ============ ABOUT ============ -->
<section class="section about" id="about">
    <div class="container about-grid">
        <div class="about-media">
            <img src="<?php echo BASE_URL; ?>/assets/img/about-farm.jpg"
                 alt="Farmers working in a green agricultural field in San Jose, Libona, Bukidnon"
                 loading="lazy">
        </div>
        <div class="about-text">
            <h2>About SJFMC</h2>
            <p>
                San Jose Farmers Multipurpose Cooperative (SJFMC) is located in
                <strong>San Jose, Libona, Bukidnon</strong>. The cooperative serves its members
                and the local agricultural community by providing resources, services, and
                opportunities that support sustainable farming and cooperative growth.
            </p>

            <div class="mv-block">
                <h3>Vision</h3>
                <p>
                    San Jose Farmers MPC envision to become a fully committed, dedicated
                    farmers living in a decent way of life through a viable, self-sustaining
                    people's organization.
                </p>
            </div>

            <div class="mv-block">
                <h3>Mission</h3>
                <p>
                    To uplift the well-being of members in increasing the productivity and
                    income provision of marketing outlet, loan access and delivery of
                    quality services.
                </p>
            </div>
        </div>
    </div>

    <!-- ===== Core Values & Objectives ===== -->
    <div class="container mv-extra">

        <!-- Core Values -->
        <div class="cv-block">
            <h3 class="mv-heading">Core Values</h3>

            <div class="cv-solid-grid">
                <div class="cv-solid-card">Integrity</div>
                <div class="cv-solid-card">Resilient</div>
                <div class="cv-solid-card">Respect</div>
                <div class="cv-solid-card">Equity</div>
                <div class="cv-solid-card">God-Fearing</div>
            </div>
        </div>

        <!-- Objectives -->
        <div class="obj-block">
            <h3 class="mv-heading">Objectives</h3>
            <p class="mv-intro">
                The cooperative pursues these objectives to fulfill its mission and vision.
            </p>

            <ol class="obj-list">
                <li>
                    <span class="obj-num" aria-hidden="true">01</span>
                    <span>To encourage members to increase its Capital Build-up and promote thrift and savings among members.</span>
                </li>
                <li>
                    <span class="obj-num" aria-hidden="true">02</span>
                    <span>To educate members of the Cooperative on its by-laws, PSP and SDP.</span>
                </li>
                <li>
                    <span class="obj-num" aria-hidden="true">03</span>
                    <span>To promote economic and social well-being of the members.</span>
                </li>
                <li>
                    <span class="obj-num" aria-hidden="true">04</span>
                    <span>To promote sound and appropriate agricultural technologies to Barangay San Jose.</span>
                </li>
                <li>
                    <span class="obj-num" aria-hidden="true">05</span>
                    <span>To work hand in hand with the government and non-government entities in the development of our Cooperative.</span>
                </li>
                <li>
                    <span class="obj-num" aria-hidden="true">06</span>
                    <span>To undertake activities, projects, and programs that would contribute to the improvement of the life of members and the community that it may serve.</span>
                </li>
                <li>
                    <span class="obj-num" aria-hidden="true">07</span>
                    <span>To develop good and mutual relationship among members and the community with gender sensitivity and respect to individual culture and religion.</span>
                </li>
                <li>
                    <span class="obj-num" aria-hidden="true">08</span>
                    <span>To generate funds and avail financing programs to sustain economic activities for members of the organization.</span>
                </li>
                <li>
                    <span class="obj-num" aria-hidden="true">09</span>
                    <span>To access and receive any assistance from foreign and local sources for the benefit and welfare of the Cooperative and its members.</span>
                </li>
                <li>
                    <span class="obj-num" aria-hidden="true">10</span>
                    <span>To provide social services to the community through tree planting, feeding program, educational assistance, and clean-up drive.</span>
                </li>
                <li>
                    <span class="obj-num" aria-hidden="true">11</span>
                    <span>To provide post-harvest services to farmer members.</span>
                </li>
            </ol>
        </div>

    </div>
</section>

<!-- ============ CONTACT ============ -->
<section class="section contact" id="contact">
    <div class="container">
        <div class="section-head">
            <h2>Contact Us</h2>
            <p>Reach out to the cooperative for inquiries and member support.</p>
        </div>

        <div class="contact-grid">
            <div class="contact-card">
                <h3>Cooperative</h3>
                <p>San Jose Farmers Multipurpose Cooperative (SJFMC)</p>
            </div>
            <div class="contact-card">
                <h3>Address</h3>
                <p>Purok 3, San Jose, Libona, Bukidnon</p>
            </div>
            <div class="contact-card">
                <h3>Phone</h3>
                <p>09924911712</p>
            </div>
            <div class="contact-card">
                <h3>Email</h3>
                <p>sjfmcoop@gmail.com</p>
            </div>
        </div>
    </div>
</section>

<!-- ============ FOOTER ============ -->
<footer class="site-footer">
    <div class="container footer-inner">
        <p>&copy; <?php echo date('Y'); ?> San Jose Farmers Multipurpose Cooperative (SJFMC). All rights reserved.</p>
    </div>
</footer>

<!-- ============ JAVASCRIPT (INLINE) ============ -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    var toggle = document.getElementById("navToggle");
    var menu   = document.getElementById("navMenu");

    if (toggle && menu) {
        toggle.addEventListener("click", function (e) {
            e.preventDefault(); 
            var open = menu.classList.toggle("open");
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
            toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
        });

        menu.addEventListener("click", function (e) {
            if (e.target.tagName === "A" && window.innerWidth <= 768) {
                menu.classList.remove("open");
                toggle.setAttribute("aria-expanded", "false");
                toggle.setAttribute("aria-label", "Open menu");
            }
        });
    }
});
</script>
</body>
</html>