/*!
 * Color mode toggler for Bootstrap's docs (https://getbootstrap.com/)
 * Copyright 2011-2024 The Bootstrap Authors
 * Licensed under the Creative Commons Attribution 3.0 Unported License.
 */

(() => {
    'use strict'
  
    const getStoredTheme = () => localStorage.getItem('theme')
    const setStoredTheme = theme => localStorage.setItem('theme', theme)
  
    const getPreferredTheme = () => {
      const storedTheme = getStoredTheme()
      if (storedTheme) {
        return storedTheme
      }
  
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    }
  
    const setTheme = theme => {
      if (theme === 'auto') {
        document.documentElement.setAttribute('data-bs-theme', (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'))
      } else {
        document.documentElement.setAttribute('data-bs-theme', theme)
      }
    }
  
    setTheme(getPreferredTheme())
  
    const showActiveTheme = (theme, focus = false) => {
      const themeSwitcher = document.querySelector('#bd-theme')
  
      if (!themeSwitcher) {
        return
      }
  
      const themeSwitcherText = document.querySelector('#bd-theme-text')
      const activeThemeIcon = document.querySelector('#bd-theme-icon')
      const btnToActive = document.querySelector(`[data-bs-theme-value="${theme}"]`)
      
      document.querySelectorAll('[data-bs-theme-value]').forEach(element => {
        element.classList.remove('active')
      })
  
      btnToActive.classList.add('active')
      activeThemeIcon.className = btnToActive.querySelector('i').className
      
      if (focus) {
        themeSwitcher.focus()
      }
    }
  
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      const storedTheme = getStoredTheme()
      if (storedTheme !== 'light' && storedTheme !== 'dark') {
        setTheme(getPreferredTheme())
      }
    })
  
    window.addEventListener('DOMContentLoaded', () => {
      showActiveTheme(getPreferredTheme())
  
      document.querySelectorAll('[data-bs-theme-value]')
        .forEach(toggle => {
          toggle.addEventListener('click', () => {
            const theme = toggle.getAttribute('data-bs-theme-value')
            setStoredTheme(theme)
            setTheme(theme)
            showActiveTheme(theme, true)
          })
        })
    })

    /**
     * Custom script to fetch and display activities
     */
    let allActivities = [];
    let filteredActivities = [];

    function populateFilters(activities) {
        const areaSet = new Set();
        const descSet = new Set();
        activities.forEach(a => {
            if (a.api_endpoint_area) areaSet.add(a.api_endpoint_area);
            if (a.api_endpoint_description) descSet.add(a.api_endpoint_description);
        });
        const areaSelect = document.getElementById('filter-area');
        const descSelect = document.getElementById('filter-description');
        // Reset
        areaSelect.innerHTML = '<option value="">Tutte</option>';
        descSelect.innerHTML = '<option value="">Tutte</option>';
        areaSet.forEach(area => {
            areaSelect.innerHTML += `<option value="${area}">${area}</option>`;
        });
        descSet.forEach(desc => {
            descSelect.innerHTML += `<option value="${desc}">${desc}</option>`;
        });
    }

    function applyFilters() {
        const area = document.getElementById('filter-area').value;
        const desc = document.getElementById('filter-description').value;
        filteredActivities = allActivities.filter(a => {
            return (!area || a.api_endpoint_area === area) && (!desc || a.api_endpoint_description === desc);
        });
    }

    function renderActivities(activities, map) {
        const activitiesList = document.getElementById('activities-list');
        activitiesList.innerHTML = '';
        let activitiesWithCoords = 0;
        activitiesList.className = 'row d-flex align-items-stretch';

        activities.forEach(activity => {
            const col = document.createElement('div');
            col.className = 'col-md-6 mb-4 d-flex'; // 2 cards per row, flex

            // Card container
            const card = document.createElement('div');
            card.className = 'card h-100 d-flex flex-column w-100';

            // Card header con description e area
            if (activity.api_endpoint_description || activity.api_endpoint_area) {
                const header = document.createElement('div');
                header.className = 'card-header';
                let headerContent = '';
                if (activity.api_endpoint_description) {
                    headerContent += `<span>${activity.api_endpoint_description}</span>`;
                }
                if (activity.api_endpoint_area) {
                    if (headerContent) headerContent += ' &middot; ';
                    headerContent += `<span>${activity.api_endpoint_area}</span>`;
                }
                header.innerHTML = headerContent;
                card.appendChild(header);
            }

            // Immagine in alto
            if (activity.cover_picture) {
                card.innerHTML += `<img src='${activity.cover_picture}' class='card-img-top' alt='cover ${activity.name}'>`;
            }

            // Card body
            const safeShortDesc = DOMPurify.sanitize((activity.description || '').substring(0, 200), {ALLOWED_TAGS: ['b','i','em','strong','a','ul','ol','li','br']});
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body d-flex flex-column h-100';
            cardBody.innerHTML = `
                <h5 class='card-title'>${activity.name}</h5>
                <p class='card-text mb-1'><span class='text-danger'>📍</span> <b>Luogo:</b> <i>${activity.location || ''}</i></p>
                <p class='card-text mb-1'><b>Inizio:</b> ${formatDateIT(activity.start_date)}</p>
                <p class='card-text mb-1'><b>Fine:</b> ${formatDateIT(activity.end_date)}</p>
                <div class='card-text mb-2'>${safeShortDesc}${activity.description && activity.description.length > 200 ? '...' : ''}</div>
                <button id='read-more-btn' class='btn btn-primary mt-auto' type='button'>Leggi tutto...</button>
            `;
            card.appendChild(cardBody);
            col.appendChild(card);
            activitiesList.appendChild(col);

            // Modal logic
            const readMoreBtn = card.querySelector('#read-more-btn');
            readMoreBtn.addEventListener('click', () => {
                let modal = document.getElementById('activityModal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.className = 'modal fade';
                    modal.id = 'activityModal';
                    modal.tabIndex = -1;
                    modal.innerHTML = `
                        <div class='modal-dialog modal-lg modal-dialog-centered'>
                            <div class='modal-content'>
                                <div class='modal-header'>
                                    <h5 class='modal-title' id='activityModalLabel'></h5>
                                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                </div>
                                <div class='modal-body' id='activityModalBody'></div>
                            </div>
                        </div>`;
                    document.body.appendChild(modal);
                }
                document.getElementById('activityModalLabel').innerHTML = activity.name;
                let modalBody = `<div class='row'>`;
                if (activity.cover_picture) {
                    modalBody += `<div class='col-md-4 text-center mb-3 mb-md-0'><img src='${activity.cover_picture}' class='img-fluid rounded' alt='cover ${activity.name}'></div>`;
                }
                modalBody += `<div class='col-md-8'>
                    <p class='mb-1'><span class='text-danger'>📍</span> <b>Luogo:</b> <i>${activity.location || ''}</i></p>
                    <p class='mb-1'><b>Inizio:</b> ${formatDateIT(activity.start_date)}</p>
                    <p class='mb-1'><b>Fine:</b> ${formatDateIT(activity.end_date)}</p>
                    <div class='mt-2'>${DOMPurify.sanitize(activity.description || '', {ALLOWED_TAGS: ['b','i','em','strong','a','ul','ol','li','br']})}</div>
                    ${activity.url ? `<a href='${activity.url}' class='btn btn-outline-primary mt-3' target='_blank'>Vai all'evento</a>` : ''}
                </div></div>`;
                document.getElementById('activityModalBody').innerHTML = modalBody;
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            });

            // Marker mappa
            if (activity.latitude && activity.longitude) {
                activitiesWithCoords++;
                const marker = L.marker([activity.latitude, activity.longitude]).addTo(map);
                marker.bindPopup(`<b>${activity.name}</b>`);
            }
        });

        if (activitiesWithCoords === 0) {
            const mapNotice = document.createElement('div');
            mapNotice.className = 'text-center text-body-secondary p-3';
            mapNotice.innerHTML = 'No activities have map coordinates.';
            document.getElementById('map-container').appendChild(mapNotice);
        }
    }

    async function fetchActivities(map) {
        const activitiesList = document.getElementById('activities-list');
        const loadingIndicator = document.getElementById('loading');
        try {
            const response = await fetch('/api/activities');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const activities = await response.json();
            allActivities = activities;
            filteredActivities = activities;
            populateFilters(activities);
            // Clear loading indicator
            loadingIndicator.remove();
            renderActivities(filteredActivities, map);
        } catch (error) {
            loadingIndicator.innerHTML = '<p class="text-center text-danger">Failed to load activities. Please try again later.</p>';
            console.error('Error fetching activities:', error);
        }
    }

    // Fetch activities when the page loads
    window.addEventListener('DOMContentLoaded', () => {
        // Initialize Leaflet map
        const map = L.map('map-container').setView([42.5, 12.5], 5); // Centered on Italy
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        fetchActivities(map);

        // Event listeners per i filtri
        document.getElementById('filter-area').addEventListener('change', () => {
            applyFilters();
            renderActivities(filteredActivities, map);
        });
        document.getElementById('filter-description').addEventListener('change', () => {
            applyFilters();
            renderActivities(filteredActivities, map);
        });
    });

    // Funzione per formattare la data in italiano
    function formatDateIT(dateString) {
        if (!dateString) return '';
        return new Date(dateString).toLocaleString('it-IT', {
            year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });
    }

})() 