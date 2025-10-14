import { useEffect, useState } from "react";
import { Menu, X, ZoomIn } from "lucide-react";
import { FaWhatsapp, FaEnvelope, FaChevronLeft, FaChevronRight } from "react-icons/fa";
import "./App.css";

export default function App() {
  const [currentEventIndex, setCurrentEventIndex] = useState(0);
  // NOTE: currentTestimonialIndex refers to the grouped testimonial slide index
  const [currentTestimonialIndex, setCurrentTestimonialIndex] = useState(0);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [selectedImageIndex, setSelectedImageIndex] = useState(0);
  const [paused, setPaused] = useState(false);

  const events = [
    { 
      name: "Event Planning & Coordination", 
      description: "From intimate gatherings to grand celebrations, we handle every detail of your event with precision and creativity. Our experienced team ensures seamless execution from concept to completion.",
      services: ["Full Event Planning", "Day-of Coordination", "Vendor Management"],
      images: ["/assets/meetandgreetevent1.jpeg", "/assets/meetandgreetevent2.jpeg", "/assets/meetandgreetevent3.jpeg"]
    },
    { 
      name: "Tent & Event Rentals", 
      description: "Transform any space into your dream venue with our premium tent and furniture rentals. We provide elegant, high-quality equipment for events of all sizes.",
      services: ["Tent Rentals", "Table & Chair Rentals", "Lighting Solutions", "Outdoor Setup"],
      images: ["/assets/birthday.jpeg", "/assets/birthday2.jpeg"]
    },
    { 
      name: "Corporate Launches", 
      description: "Make your corporate events memorable with our professional planning and sophisticated decor. We specialize in product launches, conferences, and corporate celebrations.",
      services: ["Product Launches", "Corporate Conferences", "Brand Activations", "Networking Events"],
      images: ["/assets/kottetevent1.jpeg", "/assets/kottetevent2.jpeg", "/assets/booklauch.jpeg"]
    },
    { 
      name: "Custom Event Backdrops", 
      description: "Create stunning focal points with our custom-designed backdrops. Perfect for photo opportunities, stage settings, and branded displays.",
      services: ["Floral Backdrops", "Branded Backdrops", "Photo Booth Setups", "Stage Design"],
      images: ["/assets/newbirthday.jpeg"]
    },
    { 
      name: "Custom Balloon Garlands", 
      description: "Add whimsy and elegance to your celebration with our artistic balloon installations. From organic garlands to elaborate arches, we bring color and joy to every event.",
      services: ["Balloon Garlands", "Balloon Arches", "Balloon Columns", "Custom Color Schemes"],
      images: ["/assets/babyshower1.jpeg", "/assets/babyshower2.jpeg"]
    },
    { 
      name: "Custom Branded Merchandise", 
      description: "Elevate your brand with custom merchandise that leaves a lasting impression. From promotional items to event swag, we create quality products that represent your brand.",
      services: ["Custom Apparel", "Promotional Items", "Event Bags", "Branded Gifts"],
      images: ["/assets/kottet merch.mp4"],
      isVideo: true
    }
  ];

  const testimonials = [
    { text: "Well done on our High School Alumni event decor.", author: "Phyllis Musau" },
    { text: "World class customer experience MaryAnne and team. Not just like any other plug, the attention to detail and customer service is just out of this world.", author: "Stephen Mwaganu" },
    { text: "They do a great job, decor is on point and make the whole setup look vibrant! Impeccable customer experience as well 😃 Highly recommended.", author: "Titus Kamwira" },
    { text: "Top notch service.", author: "Florence Waweru" },
  ];

  // ---- Group testimonials: short ones paired, long ones solo ----
  // threshold controls what counts as "long" (adjust 120 if you'd like)
  const LONG_THRESHOLD = 120;
  const groupedTestimonials = [];
  let tempGroup = [];

  testimonials.forEach((t) => {
    const isLong = t.text.length > LONG_THRESHOLD;
    if (isLong) {
      // flush any pending short group
      if (tempGroup.length > 0) {
        groupedTestimonials.push([...tempGroup]);
        tempGroup = [];
      }
      // long stands alone
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
  // --------------------------------------------------------------

  // Pause toggle (click slideshow or testimonials area to pause/resume)
  const togglePause = () => setPaused(prev => !prev);

  // Ensure no duplicate intervals: single useEffect to manage both timers
  useEffect(() => {
    // smooth anchor link behaviour (unchanged)
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
      link.addEventListener("click", e => {
        e.preventDefault();
        setMobileMenuOpen(false);
        const target = document.querySelector(link.getAttribute("href"));
        if (target) target.scrollIntoView({ behavior: "smooth" });
      });
    });

    // events interval (8s)
    const eventInterval = setInterval(() => {
      if (!paused) {
        setCurrentEventIndex(prev => (prev + 1) % events.length);
      }
    }, 8000);

    // testimonials interval (8s) - cycles grouped testimonials
    const testimonialInterval = setInterval(() => {
      if (!paused) {
        setCurrentTestimonialIndex(prev => (prev + 1) % groupedTestimonials.length);
      }
    }, 8000);

    return () => {
      clearInterval(eventInterval);
      clearInterval(testimonialInterval);
    };
    // groupedTestimonials.length included because it may change if testimonials data changes
  }, [paused, events.length, groupedTestimonials.length]);

  // Event prev/next (manual controls)
  const nextEvent = () => {
    setCurrentEventIndex((prev) => (prev + 1) % events.length);
  };
  const prevEvent = () => {
    setCurrentEventIndex((prev) => (prev - 1 + events.length) % events.length);
  };

  // Modal controls
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

  // Helper to open modal without toggling slideshow pause (stop propagation)
  const handleImageClick = (e, event, i) => {
    e.stopPropagation(); // prevents click from toggling pause on parent slideshow
    openModal(event, i);
  };

  // Render
  return (
    <div className="app">
      {/* Navbar */}
      <nav className="navbar">
        <div className="navbar-brand">
          <div className="logo">
            <img src="assets/logo 1.jpg" alt="Ivory Bloom Logo" />
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
          <li><a href="#events">Services</a></li>
          <li><a href="#testimonials">Testimonials</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </nav>

      {/* Hero Section */}
      <section id="home" className="hero">
        <div className="hero-content">
          <h1>Welcome to Ivory Bloom</h1>
          <h2>Premium Event Rentals, Planning and Decor Services</h2>
          <p>For corporate, family and all celebratory events</p>
          <div className="hero-buttons">
            <a href="#events" className="btn btn-gold">Our Services</a>
            <a href="#contact" className="btn btn-green">Get a Quote</a>
          </div>
        </div>
        <div className="hero-image-container">
          <img src="/assets/event3.jpeg" alt="Elegant Event Setup by Ivory Bloom Kenya" />
        </div>
      </section>

      {/* Events Slideshow */}
      <section id="events" className="events">
        <div className="container">
          <h2 style={{textAlign: "center"}}>Our Services</h2>

          <div className="events-wrapper">
            {/* clicking the slideshow toggles pause/resume */}
            <div className="slideshow" onClick={togglePause}>
              {events.map((event, index) => (
                <article
                  key={index}
                  className={`event-slide ${index === currentEventIndex ? 'active' : ''}`}
                >
                  <h3>{event.name}</h3>
                  <div className={`event-images grid-${event.images.length}`}>
                    {event.images.map((media, i) => (
                      <div 
                        key={i} 
                        className="event-image-wrapper"
                        onClick={(e) => handleImageClick(e, event, i)}
                        role="button"
                        tabIndex={0}
                        onKeyPress={(e) => e.key === 'Enter' && openModal(event, i)}
                      >
                        {event.isVideo ? (
                          <video 
                            src={media} 
                            alt={`${event.name} showcase`}
                            loop
                            muted
                            autoPlay
                            playsInline
                          />
                        ) : (
                          <img src={media} alt={`${event.name} - ${i + 1}`} />
                        )}
                        <div className="image-overlay">
                          <ZoomIn size={32} />
                          <span>Click to view details</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </article>
              ))}
            </div>

            <button onClick={(e) => { e.stopPropagation(); prevEvent(); }} className="slider-btn slider-btn-left" aria-label="Previous service">
              <FaChevronLeft size={20} />
            </button>

            <button onClick={(e) => { e.stopPropagation(); nextEvent(); }} className="slider-btn slider-btn-right" aria-label="Next service">
              <FaChevronRight size={20} />
            </button>

            {/* Dots kept only for events */}
            <div className="slider-dots">
              {events.map((_, index) => (
                <button
                  key={index}
                  onClick={(e) => { e.stopPropagation(); setCurrentEventIndex(index); }}
                  className={`dot ${currentEventIndex === index ? 'active' : ''}`}
                  aria-label={`Go to service ${index + 1}`}
                />
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Modal */}
      {selectedEvent && (
        <div className="modal-overlay" onClick={closeModal}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <button className="modal-close" onClick={closeModal} aria-label="Close modal">
              <X size={28} />
            </button>
            
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
                <h3>{selectedEvent.name}</h3>
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

                <a href="#contact" className="btn btn-gold modal-cta" onClick={closeModal}>
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

          {/* clicking the testimonials wrapper toggles pause/resume */}
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
        </div>
      </section>

      {/* Contact Section */}
      <section id="contact" className="contact">
        <div className="container">
          <h2>Get in Touch</h2>
          <p>Ready to make your event unforgettable? Reach us anytime for collaborations or bookings.</p>
          <a href="mailto:ivorybloomkenya@gmail.com" className="btn btn-gold">
            Email Us
          </a>
        </div>
      </section>

      {/* Footer */}
      <footer className="footer">
        <div className="footer-content">
          <img 
            src="assets/logo 1.jpg" 
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
        >
          <FaEnvelope />
        </a>
      </div>
    </div>
  );
}
