<?php /** * Enhanced Footer with Map  */ ?>
<footer class="footer-enhanced">
    <div class="footer-accent"></div>
    
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-4 mb-4">
                <div class="brand-section">
                    <div class="brand-header mb-3">
                        <div class="brand-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h5 class="brand-title"><?php echo APP_NAME; ?></h5>
                    </div>
                    <p class="brand-description">Connecting alumni, students, and faculty through a comprehensive digital platform for lifelong engagement and professional networking.</p>
                    
                    <div class="map-section mt-4">
                        <h6 class="map-title mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i>Our Location
                        </h6>
                        <div class="map-container">
                            <iframe 
                                src="https://www.google.com/maps?q=17.727823,75.8503355&z=16&output=embed" 
                                width="100%" 
                                height="200" 
                                style="border:0; border-radius: 12px;" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                            <div class="map-overlay">
                                <a href="https://www.google.com/maps/dir/No.+38,+N.+B.+Navale+Sinhgad+College+of+Engineering+Solapur,+Solapur+-+Pune+Highway,+Kegaon,+Maharashtra/No.+38,+Gat,+1+B,+Solapur+-+Pune+Hwy,+Kegaon,+Maharashtra+413255/@17.7278187,75.8091358,13z/data=!3m1!4b1!4m13!4m12!1m5!1m1!1s0x3bc5ce2c121bcd03:0x3e8eb2b16d919b07!2m2!1d75.8503355!2d17.727823!1m5!1m1!1s0x3bc5ce2c121bcd03:0x3e8eb2b16d919b07!2m2!1d75.8503355!2d17.727823?entry=ttu&g_ep=EgoyMDI1MTAwNy4wIKXMDSoASAFQAw%3D%3D" target="_blank" class="map-link">
                                    <i class="fas fa-external-link-alt me-2"></i>Open in Maps
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4">
                <div class="footer-section">
                    <h6 class="section-title">
                        <span>Quick Links</span>
                    </h6>
                    <ul class="footer-links">
                        <li>
                            <a href="index.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Home</span>
                            </a>
                        </li>
                        <li>
                            <a href="notices.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Notices</span>
                            </a>
                        </li>
                        <li>
                            <a href="news.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>News</span>
                            </a>
                        </li>
                        <li>
                            <a href="jobs.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Jobs</span>
                            </a>
                        </li>
                        <li>
                            <a href="events.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Events</span>
                            </a>
                        </li>
                        <li>
                            <a href="surveys.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Surveys</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4">
                <div class="footer-section">
                    <h6 class="section-title">
                        <span>Connect</span>
                    </h6>
                    <ul class="footer-links">
                        <li>
                            <a href="directory.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Directory</span>
                            </a>
                        </li>
                        <li>
                            <a href="gallery.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Gallery</span>
                            </a>
                        </li>
                        <li>
                            <a href="message.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Messages</span>
                            </a>
                        </li>
                        <li>
                            <a href="profile.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Profile</span>
                            </a>
                        </li>
                        <li>
                            <a href="settings.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                        <li>
                            <a href="feedback.php">
                                <i class="fas fa-chevron-right"></i>
                                <span>Feedback</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="footer-section">
                    <h6 class="section-title">
                        <span>Get in Touch</span>
                    </h6>
                    
                    <div class="contact-cards">
                        <div class="contact-card">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-details">
                                <div class="contact-label">Email</div>
                                <a href="mailto:contact@alumni.edu" class="contact-value">principal.nbnscoe@sinhgad.edu</a>
                            </div>
                        </div>
                        
                        <div class="contact-card">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="contact-details">
                                <div class="contact-label">Phone</div>
                                <a href="tel:+918380025630" class="contact-value">+918380025630</a>
                            </div>
                        </div>
                        
                        <div class="contact-card">
                            <div class="contact-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="contact-details">
                                <div class="contact-label">Hours</div>
                                <div class="contact-value">Mon - Fri: 9AM - 5PM</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="social-section mt-4">
                        <h6 class="social-title">Follow Our Journey</h6>
                        <div class="social-links">
                            <a href="https://www.facebook.com/officialsinhgadsolapur" class="social-link" data-network="facebook" title="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="https://x.com/nbnscoesolapur" class="social-link" data-network="twitter" title="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="https://in.linkedin.com/school/sinhgadinstitutessolapur/" class="social-link" data-network="linkedin" title="LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="https://www.instagram.com/nbnscoe/" class="social-link" data-network="instagram" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="https://www.youtube.com/hashtag/sinhgadsolapur" class="social-link" data-network="youtube" title="YouTube">
                                <i class="fab fa-youtube"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center py-4">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="copyright">
                        <i class="far fa-copyright me-1"></i>
                        <span><?php echo date('Y'); ?> <strong><?php echo APP_NAME; ?></strong>. All rights reserved.</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="footer-legal">
                        <a href="terms.php" class="legal-link">
                            <i class="fas fa-file-contract me-1"></i>Terms
                        </a>
                        <span class="separator">•</span>
                        <a href="privacy.php" class="legal-link">
                            <i class="fas fa-shield-alt me-1"></i>Privacy
                        </a>
                        <span class="separator">•</span>
                        <a href="#top" class="legal-link scroll-top">
                            <i class="fas fa-arrow-up me-1"></i>Back to Top
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
:root {
    --light-mint: #DDF4E7;
    --mint-green: #67C090;
    --teal-blue: #26667F;
    --navy-blue: #124170;
    --white: #ffffff;
    --light-gray: #f8f9fa;
    /* Variables for size reduction */
    --footer-py: 3rem; 
    --section-mb: 1rem;
    --link-mb: 0.6rem;
}

/* Main Footer */
.footer-enhanced {
    background: linear-gradient(135deg, var(--navy-blue) 0%, var(--teal-blue) 100%);
    color: var(--white);
    position: relative;
    overflow: hidden;
    margin-top: 3rem; /* Reduced margin-top */
}

/* Apply reduced padding to the main content container */
.footer-enhanced > .container {
    padding-top: var(--footer-py) !important;
    padding-bottom: var(--footer-py) !important;
}

/* Decorative Top Border */
.footer-accent {
    height: 4px;
    background: linear-gradient(90deg, 
        transparent 0%,
        var(--mint-green) 25%,
        var(--light-mint) 50%,
        var(--mint-green) 75%,
        transparent 100%
    );
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0%, 100% { opacity: 0.8; }
    50% { opacity: 1; }
}

/* Brand Section */
.brand-section {
    padding-right: 1rem;
}

.brand-header {
    display: flex;
    align-items: center;
    gap: 0.75rem; /* Reduced gap */
}

.brand-icon {
    width: 45px; /* Reduced size */
    height: 45px;
    background: linear-gradient(135deg, var(--mint-green) 0%, var(--light-mint) 100%);
    border-radius: 10px; /* Reduced radius */
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem; /* Reduced font size */
    color: var(--navy-blue);
    box-shadow: 0 3px 10px rgba(103, 192, 144, 0.3); /* Reduced shadow */
}

.brand-title {
    color: var(--light-mint);
    font-size: 1.4rem; /* Reduced font size */
    font-weight: 700;
    margin: 0;
    background: linear-gradient(135deg, var(--light-mint) 0%, var(--mint-green) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.brand-description {
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.6; /* Reduced line height */
    font-size: 0.9rem; /* Reduced font size */
    margin-top: 0.75rem; /* Reduced margin */
}

/* Map Section */
.map-section {
    background: rgba(255, 255, 255, 0.08);
    padding: 1.25rem; /* Reduced padding */
    border-radius: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    transition: all 0.3s ease;
    margin-top: 3rem !important; /* Reduced margin-top (from mt-4) */
}

.map-section:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.map-title {
    color: var(--light-mint);
    font-weight: 600;
    font-size: 0.95rem; /* Reduced font size */
    margin-bottom: 0.75rem; /* Reduced margin */
    display: flex;
    align-items: center;
}

.map-title i {
    color: var(--mint-green);
}

.map-container {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.map-container iframe {
    display: block;
    height: 160px; /* Reduced height of the map */
    filter: grayscale(20%) brightness(0.95);
    transition: filter 0.3s ease;
}

.map-container:hover iframe {
    filter: grayscale(0%) brightness(1);
}

.map-overlay {
    position: absolute;
    bottom: 10px;
    right: 10px;
    z-index: 10;
}

.map-link {
    background: rgba(18, 65, 112, 0.95);
    color: var(--light-mint);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(103, 192, 144, 0.3);
    transition: all 0.3s ease;
}

.map-link:hover {
    background: var(--mint-green);
    color: var(--navy-blue);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(103, 192, 144, 0.4);
}

/* Section Titles */
.section-title {
    color: var(--light-mint);
    font-weight: 700;
    font-size: 1.05rem; /* Reduced font size */
    margin-bottom: var(--section-mb); /* Reduced margin-bottom */
    position: relative;
    display: inline-block;
}

.section-title span {
    position: relative;
    z-index: 2;
}

.section-title::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 30px; /* Shorter underline */
    height: 2px; /* Thinner underline */
    background: linear-gradient(90deg, var(--mint-green), transparent);
    border-radius: 2px;
}

/* Footer Links */
.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: var(--link-mb); /* Reduced margin-bottom */
}

.footer-links a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    font-size: 0.9rem; /* Reduced font size */
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.3s ease;
    padding: 0.25rem 0; /* Reduced vertical padding */
}

.footer-links a:hover {
    color: var(--mint-green);
    transform: translateX(8px);
}

.footer-links a i {
    color: var(--mint-green);
    font-size: 0.7rem;
    transition: transform 0.3s ease;
}

.footer-links a:hover i {
    transform: translateX(3px);
}

/* Contact Cards */
.contact-cards {
    display: flex;
    flex-direction: column;
    gap: 0.75rem; /* Reduced gap */
}

.contact-card {
    background: rgba(255, 255, 255, 0.08);
    padding: 0.75rem; /* Reduced padding */
    border-radius: 10px; /* Reduced radius */
    display: flex;
    align-items: center;
    gap: 0.75rem; /* Reduced gap */
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.contact-card:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: translateX(5px);
    border-color: var(--mint-green);
}

.contact-icon {
    width: 40px; /* Reduced size */
    height: 40px;
    background: linear-gradient(135deg, var(--mint-green) 0%, var(--teal-blue) 100%);
    border-radius: 8px; /* Reduced radius */
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1rem; /* Reduced icon size */
    box-shadow: 0 3px 10px rgba(103, 192, 144, 0.3);
}

.contact-icon i {
    color: var(--white);
    font-size: 1.1rem;
}

.contact-details {
    flex: 1;
}

.contact-label {
    font-size: 0.7rem; /* Reduced font size */
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 0.15rem; /* Reduced margin */
}

.contact-value {
    color: var(--light-mint);
    font-size: 0.9rem; /* Reduced font size */
    font-weight: 500;
    text-decoration: none;
    transition: color 0.3s ease;
}

a.contact-value:hover {
    color: var(--mint-green);
}

/* Social Media */
.social-section {
    background: rgba(255, 255, 255, 0.08);
    padding: 1.25rem; /* Reduced padding */
    border-radius: 10px; /* Reduced radius */
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: 3rem !important; /* Reduced margin-top (from mt-4) */
}

.social-title {
    color: var(--light-mint);
    font-weight: 600;
    font-size: 0.95rem; /* Reduced font size */
    margin-bottom: 0.75rem; /* Reduced margin */
}

.social-links {
    display: flex;
    gap: 0.5rem; /* Reduced gap */
    flex-wrap: wrap;
}

.social-link {
    width: 40px; /* Reduced size */
    height: 40px;
    border-radius: 10px; /* Reduced radius */
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    color: var(--white);
    text-decoration: none;
    font-size: 1.1rem; /* Reduced font size */
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.15);
    position: relative;
    overflow: hidden;
}

.social-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, var(--mint-green), var(--teal-blue));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.social-link:hover::before {
    opacity: 1;
}

.social-link i {
    position: relative;
    z-index: 2;
}

.social-link:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 6px 20px rgba(103, 192, 144, 0.4);
    border-color: var(--mint-green);
}

.social-link[data-network="facebook"]:hover { background: #1877f2; }
.social-link[data-network="twitter"]:hover { background: #1da1f2; }
.social-link[data-network="linkedin"]:hover { background: #0077b5; }
.social-link[data-network="instagram"]:hover { background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); }
.social-link[data-network="youtube"]:hover { background: #ff0000; }

/* Footer Bottom */
.footer-bottom {
    background: rgba(0, 0, 0, 0.25);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
}

.footer-bottom .container .row {
    padding-top: 1rem !important; /* Reduced padding-top */
    padding-bottom: 1rem !important; /* Reduced padding-bottom */
}

.copyright {
    color: rgba(255, 255, 255, 0.75);
    font-size: 0.9rem; /* Reduced font size */
    display: flex;
    align-items: center;
}

.copyright strong {
    color: var(--light-mint);
    margin: 0 0.25rem;
}

.footer-legal {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.legal-link {
    color: rgba(255, 255, 255, 0.75);
    text-decoration: none;
    font-size: 0.85rem; /* Reduced font size */
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
}

.legal-link:hover {
    color: var(--mint-green);
    transform: translateY(-2px);
}

.legal-link i {
    font-size: 0.8rem;
}

.separator {
    margin: 0 0.5rem; /* Reduced margin */
    color: rgba(255, 255, 255, 0.4);
}

/* Scroll to Top Animation */
.scroll-top {
    position: relative;
}

.scroll-top:hover i {
    animation: bounce 0.6s infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

/* Responsive Design */
@media (max-width: 992px) {
    .footer-enhanced > .container {
        padding-top: 2.5rem !important; /* Adjusted responsive padding */
        padding-bottom: 2.5rem !important;
    }

    .col-lg-4.mb-4, .col-lg-2.col-md-6.mb-4 {
        margin-bottom: 1.5rem !important; /* Reduced bottom margin on columns */
    }

    .brand-section {
        margin-bottom: 1.5rem;
    }
}

@media (max-width: 768px) {
    .brand-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .brand-icon {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
    }
    
    .map-container iframe {
        height: 150px; /* Further reduced map height */
    }
    
    .contact-card {
        padding: 0.85rem;
    }
    
    .contact-icon {
        width: 40px;
        height: 40px;
    }
    
    .social-link {
        width: 38px;
        height: 38px;
        font-size: 1rem;
    }
    
    .footer-legal {
        justify-content: center;
        margin-top: 1rem;
    }
    
    .copyright {
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .section-title {
        font-size: 1rem;
    }
    
    .contact-cards {
        gap: 0.75rem;
    }
    
    .social-links {
        justify-content: center;
    }
    
    .footer-legal {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .separator {
        display: none;
    }
}
</style>

<script>
// Smooth scroll to top
document.addEventListener('DOMContentLoaded', function() {
    const scrollTop = document.querySelector('.scroll-top');
    if (scrollTop) {
        scrollTop.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});
</script>