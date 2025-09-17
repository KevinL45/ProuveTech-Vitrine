        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in-section').forEach(section => {
            observer.observe(section);
        });

        // Video player functionality
        let video = null;
        let isPlaying = false;

        function playVideo() {
            video = document.getElementById('introVideo');
            const placeholder = document.getElementById('videoPlayer');
            const playButton = placeholder.querySelector('.play-button');
            const thumbnail = placeholder.querySelector('.video-thumbnail');
            const controls = document.getElementById('customControls');
            
            video.style.display = 'block';
            playButton.style.display = 'none';
            thumbnail.style.display = 'none';
            controls.style.display = 'flex';
            
            video.play();
            isPlaying = true;
            
            // Initialize video controls
            video.addEventListener('loadedmetadata', function() {
                document.getElementById('duration').textContent = formatTime(video.duration);
            });
            
            video.addEventListener('timeupdate', function() {
                const progress = (video.currentTime / video.duration) * 100;
                document.getElementById('progressFill').style.width = progress + '%';
                document.getElementById('currentTime').textContent = formatTime(video.currentTime);
            });
            
            video.addEventListener('ended', function() {
                isPlaying = false;
                const playPauseBtn = document.querySelector('.play-pause-btn');
                playPauseBtn.innerHTML = '<span>▶</span>';
            });
        }

        function togglePlayPause() {
            if (!video) return;
            
            const playPauseBtn = document.querySelector('.play-pause-btn');
            if (isPlaying) {
                video.pause();
                playPauseBtn.innerHTML = '<span>▶</span>';
            } else {
                video.play();
                playPauseBtn.innerHTML = '<span class="pause-icon">❚❚</span>';
            }
            isPlaying = !isPlaying;
        }

        function seek(event) {
            if (!video) return;
            
            const progressBar = event.currentTarget;
            const rect = progressBar.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const percentage = x / rect.width;
            video.currentTime = percentage * video.duration;
        }

        function toggleMute() {
            if (!video) return;
            
            const volumeBtn = document.querySelector('.volume-btn');
            video.muted = !video.muted;
            volumeBtn.innerHTML = video.muted ? '<span>🔇</span>' : '<span>🔊</span>';
        }

        function toggleFullscreen() {
            if (!video) return;
            
            const videoFrame = document.querySelector('.video-frame');
            
            if (!document.fullscreenElement) {
                if (videoFrame.requestFullscreen) {
                    videoFrame.requestFullscreen();
                } else if (videoFrame.webkitRequestFullscreen) {
                    videoFrame.webkitRequestFullscreen();
                } else if (videoFrame.mozRequestFullScreen) {
                    videoFrame.mozRequestFullScreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = Math.floor(seconds % 60);
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        }

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Scroll to form
        function scrollToForm(e) {
            e.preventDefault();
            document.querySelector('#signup-form').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        // Form handling
        const form = document.getElementById('betaForm');

        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('.submit-btn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoader = submitBtn.querySelector('.btn-loader');
            const formSuccess = form.querySelector('.form-success');
            
            // Show loading state
            btnText.style.display = 'none';
            btnLoader.style.display = 'flex';
            submitBtn.disabled = true;
            
            // Collecter les données du formulaire
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // OPTION 2: EmailJS - ACTIVE
            // Remplacez YOUR_SERVICE_ID et YOUR_TEMPLATE_ID par vos identifiants EmailJS
            const templateParams = {
                firstName: data.firstName,
                lastName: data.lastName,
                email: data.email,
                profile: data.profile,
                to_email: 'kevinlokoka@gmail.com', // Email de destination
                from_name: data.firstName + ' ' + data.lastName,
                reply_to: data.email
            };
            // Envoyer l'email via EmailJS
            emailjs.send('service_suc6pdj', 'template_ocwxljg', templateParams)
                .then(function(response) {
                    console.log('SUCCESS!', response.status, response.text);
                    showSuccess();
                }, function(error) {
                    console.error('FAILED...', error);
                    alert('Erreur lors de l\'envoi. Veuillez réessayer.');
                    resetButton();
                });
            
            function showSuccess() {
                // Hide form fields
                const formGroups = form.querySelectorAll('.form-group');
                formGroups.forEach(group => {
                    group.style.display = 'none';
                });
                submitBtn.style.display = 'none';
                
                // Show success message
                formSuccess.style.display = 'block';
                
                // Reset form for next use
                setTimeout(() => {
                    form.reset();
                }, 3000);
            }
            
            function resetButton() {
                btnText.style.display = 'block';
                btnLoader.style.display = 'none';
                submitBtn.disabled = false;
            }
        });

        // Parallax effect on mouse move
        document.addEventListener('mousemove', (e) => {
            const shapes = document.querySelectorAll('.floating-shape');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 20;
                const xPos = (x - 0.5) * speed;
                const yPos = (y - 0.5) * speed;
                
                shape.style.transform = `translate(${xPos}px, ${yPos}px)`;
            });
        });