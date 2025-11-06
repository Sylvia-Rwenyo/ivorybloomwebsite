// Admin Panel JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const dashboard = document.querySelector('.dashboard');
    const loginContainer = document.querySelector('.login-container');
    
    // Handle login form submission
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        try {
            const response = await fetch('../api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Store token and user info
                localStorage.setItem('token', data.data.token);
                localStorage.setItem('user', JSON.stringify(data.data.user));
                
                // Show dashboard
                loginContainer.style.display = 'none';
                dashboard.style.display = 'block';
                
                // Load initial data
                loadEvents();
                loadTestimonials();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Login error:', error);
            alert('An error occurred during login. Please try again.');
        }
    });
    
    // Load events
    async function loadEvents() {
        try {
            const response = await fetch('../api/events.php', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('token')
                }
            });
            const data = await response.json();
            
            if (data.success) {
                const eventsTable = document.getElementById('eventsTable');
                eventsTable.innerHTML = data.data.map(event => `
                    <tr>
                        <td>${event.name}</td>
                        <td>${event.description.substring(0, 100)}...</td>
                        <td>
                            <button onclick="editEvent(${event.id})" class="btn btn-secondary btn-sm">Edit</button>
                            <button onclick="deleteEvent(${event.id})" class="btn btn-danger btn-sm">Delete</button>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Error loading events:', error);
        }
    }
    
    // Load testimonials
    async function loadTestimonials() {
        try {
            const response = await fetch('../api/testimonials.php', {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('token')
                }
            });
            const data = await response.json();
            
            if (data.success) {
                const testimonialsTable = document.getElementById('testimonialsTable');
                testimonialsTable.innerHTML = data.data.map(testimonial => `
                    <tr>
                        <td>${testimonial.author_name}</td>
                        <td>${testimonial.testimonial_text.substring(0, 100)}...</td>
                        <td>
                            <button onclick="editTestimonial(${testimonial.id})" class="btn btn-secondary btn-sm">Edit</button>
                            <button onclick="deleteTestimonial(${testimonial.id})" class="btn btn-danger btn-sm">Delete</button>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('Error loading testimonials:', error);
        }
    }
    
    // Check if user is already logged in
    const token = localStorage.getItem('token');
    if (token) {
        loginContainer.style.display = 'none';
        dashboard.style.display = 'block';
        loadEvents();
        loadTestimonials();
    }
});