import { useEffect, useState } from "react";
import { Menu, X, ZoomIn, Calendar, Layers, PartyPopper, Tent, Sparkles, ShoppingBag , Expand} from "lucide-react";
import { FaWhatsapp, FaEnvelope, FaChevronLeft, FaChevronRight } from "react-icons/fa";
import "./App.css";

// API Configuration
const API_BASE_URL = "https://ivorybloom.co.ke/cms/api"; 


export default function App() {
  const [currentTestimonialIndex, setCurrentTestimonialIndex] = useState(0);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [selectedImageIndex, setSelectedImageIndex] = useState(0);
  const [paused, setPaused] = useState(false);
  const [services, setServices] = useState([]);
  const ourServices = [
    { name: "Event Planning & Coordination", icon: Calendar },
    { name: "Custom Event Backdrops", icon: Layers },
    { name: "Balloon Garlands", icon: PartyPopper },
    { name: "Tent & Event Rentals", icon: Tent },
    { name: "Event Décor", icon: Sparkles },
    { name: "Branded Merchandise", icon: ShoppingBag },
  ];
  
  // State for API data
  const [events, setEvents] = useState([]);
  const [testimonials, setTestimonials] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Fetch events from API
  useEffect(() => {
    const fetchEvents = async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/events.php`);
        const data = await response.json();
        
        if (data.success) {
          // Transform API data to match component structure
          const transformedEvents = data.events.map(event => ({
            name: event.name,
            description: event.description,
            services: event.services || [],
            images: event.images.map(img => img.file_path),
            isVideo: event.images.some(img => img.file_type === 'video')
          }));
          
          setEvents(transformedEvents);
          
          // Extract unique services for the services section
          const allServices = transformedEvents.flatMap(e => e.services);
          const uniqueServices = [...new Set(allServices)];
          setServices(uniqueServices);
        } else {
          setError(data.message);
        }
      } catch (err) {
        console.error("Error fetching events:", err);
        setError("Failed to load events");
      }
    };

    fetchEvents();
  }, []);

  // Fetch testimonials from API
  useEffect(() => {
    const fetchTestimonials = async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/testimonials.php`);
        const data = await response.json();
        
        if (data.success) {
          // Transform API data to match component structure
          const transformedTestimonials = data.testimonials.map(testimonial => ({
            text: testimonial.testimonial_text,
            author: testimonial.author_name
          }));
          
          setTestimonials(transformedTestimonials);
        } else {
          setError(data.message);
        }
      } catch (err) {
        console.error("Error fetching testimonials:", err);
        setError("Failed to load testimonials");
      } finally {
        setLoading(false);
      }
    };

    fetchTestimonials();
  }, []);

  // Group testimonials logic
  const LONG_THRESHOLD = 120;
  const groupedTestimonials = [];
  let tempGroup = [];

  testimonials.forEach((t) => {
    const isLong = t.text.length > LONG_THRESHOLD;
    if (isLong) {
      if (tempGroup.length > 0) {
        groupedTestimonials.push([...tempGroup]);
        tempGroup = [];
      }
      groupedTestimonials.push([t]);
    } else {
      tempGroup.push(t);
      if (tempGroup.length === 2) {
        groupedTestimonials.push([...tempGroup]);
        tempGroup = [];
      }
    }
  });
  if (tempGroup.length > 0) groupedTestimonials.push([...tempGroup]);

  const togglePause = () => setPaused(prev => !prev);

  useEffect(() => {
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
      link.addEventListener("click", e => {
        e.preventDefault();
        setMobileMenuOpen(false);
        const target = document.querySelector(link.getAttribute("href"));
        if (target) target.scrollIntoView({ behavior: "smooth" });
      });
    });

    const testimonialInterval = setInterval(() => {
      if (!paused && groupedTestimonials.length > 0) {
        setCurrentTestimonialIndex(prev => (prev + 1) % groupedTestimonials.length);
      }
    }, 8000);

    return () => {
      clearInterval(testimonialInterval);
    };
  }, [paused, groupedTestimonials.length]);

  const openModal = (event, imageIndex = 0) => {
    setSelectedEvent(event);
    setSelectedImageIndex(imageIndex);
    document.body.style.overflow = 'hidden';
  };

  const closeModal = () => {
    setSelectedEvent(null);
    setSelectedImageIndex(0);
    document.body.style.overflow = 'unset';
  };

  const nextModalImage = () => {
    if (selectedEvent) {
      setSelectedImageIndex((prev) => (prev + 1) % selectedEvent.images.length);
    }
  };

  const prevModalImage = () => {
    if (selectedEvent) {
      setSelectedImageIndex((prev) => (prev - 1 + selectedEvent.images.length) % selectedEvent.images.length);
    }
  };

  // Show loading state
  if (loading) {
    return (
      <div className="app" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh' }}>
        <div style={{ textAlign: 'center' }}>
          <h2>Loading...</h2>
        </div>
      </div>
    );
  }

  // Show error state
  if (error) {
    return (
      <div className="app" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh' }}>
        <div style={{ textAlign: 'center', color: 'red' }}>
          <h2>Error</h2>
          <p>{error}</p>
        </div>
      </div>
    );
  }



  return (
    <div className="app">
      {/* Navbar */}
      <nav className="navbar">
        <div className="navbar-brand">
          <div className="logo">
            <img src="assets/logo1-noBg.png" alt="Ivory Bloom Logo" />
          </div>
          <h1><b>Ivory Bloom</b></h1>
        </div>
        
        <button 
          className="hamburger" 
          onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
          aria-label="Toggle menu"
        >
          {mobileMenuOpen ? <X size={24} /> : <Menu size={24} />}
        </button>

        <ul className={`navbar-menu ${mobileMenuOpen ? 'active' : ''}`}>
          <li><a href="#home">Home</a></li>
          <li><a href="#services">Services</a></li>
          <li><a href="#events">Gallery</a></li>
          <li><a href="#testimonials">Testimonials</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </nav>


      {/* Hero Section */}
      <section id="home" className="hero">
        <div className="hero-content">
          <h1>Ivory Bloom</h1>
          <h2>
            Premium Rentals <span></span> Stylish Tents<span></span> Timeless Decor
          </h2>

          <p>Event setup with lasting impressions ~ for corporate & private events in Nairobi.</p>
          <div className="hero-buttons">
            <a href="#services" className="btn btn-dark">Our Services</a>
            <a href="#contact" className="btn btn-roseGold">Get a Quote</a>
          </div>
        </div>
        <div className="hero-image-container">
          <img src="/assets/meetandgreetevent3.jpeg" alt="Elegant Event Setup by Ivory Bloom Kenya" />
        </div>
      </section>

      {/* Services Section */}
      <section id="services" className="services-section">
        <div className="services-left">
          <h2>Our Services</h2>
          <p>
            We plan and style events with elegance, tailoring each detail to fit the moment and your taste. 
            Whether it's a baby shower, launch, or dinner, we make every occasion feel special.
          </p>
        </div>

        <div className="services-grid">
          {ourServices.map((service, index) => {
            const IconComponent = service.icon;
            return (
              <div key={index} className="service-card">
                <div className="service-icon">
                  <IconComponent size={24} strokeWidth={1.5} />
                </div>
                <h3>{service.name}</h3>
              </div>
            );
          })}
        </div>
          
      </section>

      {/* Events Gallery */}
      <section id="events" className="events-gallery">
        <div className="container">
          <h2>Curated Experiences</h2>
          <p className="gallery-intro">Our portfolio of artfully styled moments</p>
          
          {events.length === 0 ? (
            <p style={{ textAlign: 'center' }}>No events available at the moment.</p>
          ) : (
            <div className="gallery-grid">
              {events.map((event, eventIndex) => (
                event.images.map((media, imageIndex) => (
                  <div 
                    key={`${eventIndex}-${imageIndex}`}
                    className="gallery-item"
                    onClick={() => openModal(event, imageIndex)}
                    role="button"
                    tabIndex={0}
                    onKeyPress={(e) => e.key === 'Enter' && openModal(event, imageIndex)}
                  >
                    {event.isVideo ? (
                      <video 
                        src={media} 
                        alt={`${event.name} showcase`}
                        loop
                        muted
                        playsInline
                      />
                    ) : (
                      <img src={media} alt={`${event.name}`} />
                    )}
                    <div className="gallery-overlay">
                        <Expand size={32}  color="rgb(253, 246, 236)" />
                      <span className="gallery-title">{event.name}</span>
                    </div>
                  </div>
                ))
              ))}
            </div>
          )}
        </div>
      </section>

      {/* Modal */}
      {selectedEvent && (
        <div className="modal-overlay" onClick={closeModal}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <button className="modal-close" onClick={closeModal} aria-label="Close modal">
              <X size={28} />
            </button>

            <div className="modal-header">
              <h3>{selectedEvent.name}</h3>
            </div>
            
            <div className="modal-body">
              <div className="modal-gallery">
                {selectedEvent.images.length > 1 && (
                  <button 
                    className="modal-nav modal-nav-left" 
                    onClick={prevModalImage}
                    aria-label="Previous image"
                  >
                    <FaChevronLeft size={24} />
                  </button>
                )}
                
                <div className="modal-image-container">
                  {selectedEvent.isVideo ? (
                    <video 
                      src={selectedEvent.images[selectedImageIndex]} 
                      controls
                      autoPlay
                      loop
                    />
                  ) : (
                    <img 
                      src={selectedEvent.images[selectedImageIndex]} 
                      alt={`${selectedEvent.name} - ${selectedImageIndex + 1}`}
                    />
                  )}
                </div>

                {selectedEvent.images.length > 1 && (
                  <button 
                    className="modal-nav modal-nav-right" 
                    onClick={nextModalImage}
                    aria-label="Next image"
                  >
                    <FaChevronRight size={24} />
                  </button>
                )}

                {selectedEvent.images.length > 1 && (
                  <div className="modal-image-counter">
                    {selectedImageIndex + 1} / {selectedEvent.images.length}
                  </div>
                )}
              </div>

              <div className="modal-info">
                <p className="modal-description">{selectedEvent.description}</p>
                
                <div className="modal-services">
                  <h4>What We Offer:</h4>
                  <div className="services-grid">
                    {selectedEvent.services.map((service, idx) => (
                      <div key={idx} className="service-tag">
                        {service}
                      </div>
                    ))}
                  </div>
                </div>

                <a href="#contact" className="btn btn-dark modal-cta" onClick={closeModal}>
                  Get a Quote
                </a>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Testimonials */}
      <section id="testimonials" className="testimonials">
        <div className="container">
          <h2 style={{ textAlign: "center" }}>What Our Clients Say</h2>

          {groupedTestimonials.length === 0 ? (
            <p style={{ textAlign: 'center' }}>No testimonials available at the moment.</p>
          ) : (
            <div className="testimonials-wrapper" onClick={togglePause}>
              {groupedTestimonials.map((group, index) => (
                <div
                  key={index}
                  className={`testimonial-slide ${index === currentTestimonialIndex ? "active" : ""}`}
                >
                  <div className={`testimonial-group ${group.length === 1 ? "single" : "pair"}`}>
                    {group.map((testimonial, i) => (
                      <div key={i} className="testimonial-card">
                        <p className="testimonial-text">"{testimonial.text}"</p>
                        <p className="testimonial-author">— {testimonial.author}</p>
                      </div>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </section>

      {/* Contact Section */}
      <section id="contact" className="contact">
        <div className="container">
            <h2>Get in Touch</h2>
          <p>Ready to make your event unforgettable? Reach us anytime for collaborations or bookings.</p>
          <a href="https://wa.me/254716640973" className="btn btn-dark"  style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', width: 'fit-content' }}>
            Contact Us &nbsp; <FaWhatsapp />
          </a>
        </div>
      </section>

      {/* Footer */}
      <footer className="footer">
        <div className="footer-content">
          <img 
            src="assets/logo1.jpeg" 
            alt="Ivory Bloom Logo" 
            className="footer-logo" 
          />
          <div className="footer-text">
            <p>Premium Event Planning & Decor Services</p>
            <p>&copy; 2025 Ivory Bloom Kenya. All rights reserved.</p>
          </div>
        </div>
        <span className="footer-credit">
          Designed & Developed by <a href="https://lnk.ink/Sr-portfolio" target="_blank" rel="noopener noreferrer"><b>Sylvia Rwenyo</b></a>
        </span>
      </footer>

      {/* Floating Contact Buttons */}
      <div className="floating-buttons">
        <a
          href="https://wa.me/254716640973"
          target="_blank"
          rel="noopener noreferrer"
          className="floating-btn whatsapp"
          aria-label="Contact us on WhatsApp"
        >
          <FaWhatsapp />
        </a>
        <a 
          href="mailto:ivorybloomkenya@gmail.com" 
          className="floating-btn email"
          aria-label="Email us"
          style={{ color: "#555" }}
        >
          <FaEnvelope />
        </a>
      </div>
    </div>

  );
}