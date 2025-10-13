/*!
 * Color mode toggler for Bootstrap's docs (https://getbootstrap.com/)
 * Copyright 2011-2024 The Bootstrap Authors
 * Licensed under the Creative Commons Attribution 3.0 Unported License.
 */

(() => {
    "use strict";

    const getStoredTheme = () => localStorage.getItem("theme");
    const setStoredTheme = (theme) => localStorage.setItem("theme", theme);

    const getPreferredTheme = () => {
        const storedTheme = getStoredTheme();
        if (storedTheme) {
            return storedTheme;
        }

        return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    };

    const setTheme = (theme) => {
        if (theme === "auto") {
            document.documentElement.setAttribute("data-bs-theme", window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
        } else {
            document.documentElement.setAttribute("data-bs-theme", theme);
        }
        
        // Update logo visibility based on theme
        updateLogoVisibility();
    };

    const updateLogoVisibility = () => {
        const lightLogo = document.querySelector('.light-logo');
        const darkLogo = document.querySelector('.dark-logo');
        
        if (!lightLogo || !darkLogo) return;
        
        const currentTheme = document.documentElement.getAttribute('data-bs-theme');
        const isDark = currentTheme === 'dark' || 
                      (currentTheme === 'auto' && window.matchMedia("(prefers-color-scheme: dark)").matches);
        
        if (isDark) {
            lightLogo.style.display = 'none';
            darkLogo.style.display = 'inline-block';
        } else {
            lightLogo.style.display = 'inline-block';
            darkLogo.style.display = 'none';
        }
    };

    setTheme(getPreferredTheme());

    const showActiveTheme = (theme, focus = false) => {
        const themeSwitcher = document.querySelector("#bd-theme");

        if (!themeSwitcher) {
            return;
        }

        const themeSwitcherText = document.querySelector("#bd-theme-text");
        const activeThemeIcon = document.querySelector("#bd-theme-icon");
        const btnToActive = document.querySelector(`[data-bs-theme-value="${theme}"]`);

        document.querySelectorAll("[data-bs-theme-value]").forEach((element) => {
            element.classList.remove("active");
        });

        btnToActive.classList.add("active");
        activeThemeIcon.className = btnToActive.querySelector("i").className;

        if (focus) {
            themeSwitcher.focus();
        }
    };

    window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
        const storedTheme = getStoredTheme();
        if (storedTheme !== "light" && storedTheme !== "dark") {
            setTheme(getPreferredTheme());
        }
        // Update logo visibility when system theme changes
        updateLogoVisibility();
    });

    window.addEventListener("DOMContentLoaded", () => {
        showActiveTheme(getPreferredTheme());
        
        // Initialize logo visibility
        updateLogoVisibility();

        document.querySelectorAll("[data-bs-theme-value]").forEach((toggle) => {
            toggle.addEventListener("click", () => {
                const theme = toggle.getAttribute("data-bs-theme-value");
                setStoredTheme(theme);
                setTheme(theme);
                showActiveTheme(theme, true);
            });
        });

        // Initialize Leaflet map
        const HOME_COORDS = [42.5, 12.5];
        const HOME_ZOOM = 5;
        const map = L.map("map-container").setView(HOME_COORDS, HOME_ZOOM); // Centered on Italy
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        // Custom Home Button
        const homeControl = L.Control.extend({
            options: { position: "topleft" },
            onAdd: function (map) {
                const container = L.DomUtil.create("div", "leaflet-bar leaflet-control leaflet-control-custom");
                container.style.backgroundColor = "white";
                container.style.width = "34px";
                container.style.height = "34px";
                container.style.display = "flex";
                container.style.alignItems = "center";
                container.style.justifyContent = "center";
                container.style.cursor = "pointer";
                container.title = "Torna alla vista iniziale";
                container.innerHTML = '<i class="bi bi-house-door" style="font-size: 1.2rem; color: #000;"></i>';
                container.onclick = function () {
                    map.setView(HOME_COORDS, HOME_ZOOM);
                };
                return container;
            },
        });
        map.addControl(new homeControl());

        // Fix per mappa in collapse
        const collapseMap = document.getElementById("collapseMap");
        if (collapseMap) {
            collapseMap.addEventListener("shown.bs.collapse", function () {
                setTimeout(() => {
                    map.invalidateSize();
                }, 200);
            });
        }

        fetchActivities(map);

        // Event listeners per i filtri
        document.getElementById("filter-area").addEventListener("change", () => {
            if (typeof Sentry !== 'undefined' && Sentry.startSpan) {
                Sentry.startSpan(
                    {
                        op: "ui.interaction",
                        name: "Filter Area Change",
                    },
                    () => {
                        applyFilters();
                        renderActivities(filteredActivities, map);
                        renderCalendar(filteredActivities);
                    }
                );
            } else {
                applyFilters();
                renderActivities(filteredActivities, map);
                renderCalendar(filteredActivities);
            }
        });
        document.getElementById("filter-description").addEventListener("change", () => {
            if (typeof Sentry !== 'undefined' && Sentry.startSpan) {
                Sentry.startSpan(
                    {
                        op: "ui.interaction",
                        name: "Filter Description Change",
                    },
                    () => {
                        applyFilters();
                        renderActivities(filteredActivities, map);
                        renderCalendar(filteredActivities);
                    }
                );
            } else {
                applyFilters();
                renderActivities(filteredActivities, map);
                renderCalendar(filteredActivities);
            }
        });

        // Gestione pulsante Mostra/Nascondi eventi passati
        const showPastBtn = document.getElementById("show-past-events-btn");
        function updateShowPastBtn() {
            if (showPastEvents) {
                showPastBtn.classList.remove("btn-outline-secondary");
                showPastBtn.classList.add("btn-secondary");
                showPastBtn.textContent = "Nascondi eventi passati";
            } else {
                showPastBtn.classList.add("btn-outline-secondary");
                showPastBtn.classList.remove("btn-secondary");
                showPastBtn.textContent = "Mostra eventi passati";
            }
        }
        showPastBtn.addEventListener("click", () => {
            if (typeof Sentry !== 'undefined' && Sentry.startSpan) {
                Sentry.startSpan(
                    {
                        op: "ui.click",
                        name: "Show Past Events Toggle",
                    },
                    () => {
                        showPastEvents = !showPastEvents;
                        updateShowPastBtn();
                        applyFilters();
                        renderActivities(filteredActivities, map);
                        // Se la mappa è visibile, ricalcola la dimensione
                        if (collapseMap && collapseMap.classList.contains("show")) {
                            setTimeout(() => {
                                map.invalidateSize();
                            }, 200);
                        }
                        renderCalendar(filteredActivities);
                    }
                );
            } else {
                showPastEvents = !showPastEvents;
                updateShowPastBtn();
                applyFilters();
                renderActivities(filteredActivities, map);
                // Se la mappa è visibile, ricalcola la dimensione
                if (collapseMap && collapseMap.classList.contains("show")) {
                    setTimeout(() => {
                        map.invalidateSize();
                    }, 200);
                }
                renderCalendar(filteredActivities);
            }
        });
        updateShowPastBtn();

        // Fix per ridisegnare il calendario quando il collapse viene aperto
        const collapseCalendar = document.getElementById("collapseCalendar");
        if (collapseCalendar) {
            collapseCalendar.addEventListener("shown.bs.collapse", function () {
                if (window.fcInstance && typeof window.fcInstance.updateSize === "function") {
                    window.fcInstance.updateSize();
                } else if (window.fcInstance && typeof window.fcInstance.render === "function") {
                    window.fcInstance.render();
                }
            });
        }
    });

    /**
     * Custom script to fetch and display activities
     */
    let allActivities = [];
    let filteredActivities = [];
    let showPastEvents = false;
    let activityMarkers = [];

    function populateFilters(activities) {
        const areaSet = new Set();
        const descSet = new Set();
        activities.forEach((a) => {
            if (a.api_endpoint_area) areaSet.add(a.api_endpoint_area);
            if (a.api_endpoint_description) descSet.add(a.api_endpoint_description);
        });
        const areaSelect = document.getElementById("filter-area");
        const descSelect = document.getElementById("filter-description");
        // Reset
        areaSelect.innerHTML = '<option value="">Tutte</option>';
        descSelect.innerHTML = '<option value="">Tutte</option>';
        
        // Ordina le aree alfabeticamente
        const sortedAreas = Array.from(areaSet).sort();
        sortedAreas.forEach((area) => {
            areaSelect.innerHTML += `<option value="${area}">${area}</option>`;
        });
        
        // Ordina le descrizioni alfabeticamente
        const sortedDescriptions = Array.from(descSet).sort();
        sortedDescriptions.forEach((desc) => {
            descSelect.innerHTML += `<option value="${desc}">${desc}</option>`;
        });
    }

    function applyFilters() {
        const area = document.getElementById("filter-area").value;
        const desc = document.getElementById("filter-description").value;
        const now = new Date();
        filteredActivities = allActivities.filter((a) => {
            const isFuture = !a.start_date || new Date(a.start_date) >= now;
            // Se showPastEvents è true, mostra anche i passati, altrimenti solo futuri
            const dateFilter = showPastEvents ? true : isFuture;
            return dateFilter && (!area || a.api_endpoint_area === area) && (!desc || a.api_endpoint_description === desc);
        });
        // Ordina per data di inizio crescente
        filteredActivities.sort((a, b) => new Date(a.start_date) - new Date(b.start_date));
    }

    function renderActivities(activities, map) {
        const activitiesList = document.getElementById("activities-list");
        activitiesList.innerHTML = "";
        let activitiesWithCoords = 0;
        activitiesList.className = "activities-flex";

        // Rimuovi tutti i marker precedenti
        if (window.activityMarkers && Array.isArray(window.activityMarkers)) {
            window.activityMarkers.forEach((marker) => marker.remove());
        }
        window.activityMarkers = [];

        activities.forEach((activity) => {
            const col = document.createElement("div");
            col.className = "mb-4 d-flex"; // flex card
            if (activity.id) {
                col.id = `event-${activity.id}`;
            }

            // Card container
            const card = document.createElement("div");
            card.className = "card h-100 d-flex flex-column w-100";

            // Card header con description e area
            if (activity.api_endpoint_description || activity.api_endpoint_area) {
                const header = document.createElement("div");
                header.className = "card-header";
                let headerContent = "";
                if (activity.api_endpoint_description) {
                    headerContent += `<span>${activity.api_endpoint_description}</span>`;
                }
                if (activity.api_endpoint_area) {
                    if (headerContent) headerContent += " &middot; ";
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
            const shortDesc = (activity.description || "").substring(0, 200);
            const cardBody = document.createElement("div");
            cardBody.className = "card-body d-flex flex-column h-100";
            cardBody.innerHTML = `
                <h5 class='card-title'>${activity.name}</h5>
                <p class='card-text mb-1'><span class='text-danger'>📍</span> <b>Luogo:</b> <i>${activity.location || ""}</i></p>
                <p class='card-text mb-1'><b>Inizio:</b> ${formatDateIT(activity.start_date)}</p>
                <p class='card-text mb-1'><b>Fine:</b> ${formatDateIT(activity.end_date)}</p>
                <div class='card-text mb-2'>${shortDesc}${activity.description && activity.description.length > 200 ? "..." : ""}</div>
                <button id='read-more-btn-${activity.id}' class='btn btn-primary mt-auto' type='button'>Leggi tutto...</button>
            `;
            card.appendChild(cardBody);
            col.appendChild(card);
            activitiesList.appendChild(col);

            // Force all links in the card description to open in new tabs
            const cardLinks = card.querySelectorAll('a');
            cardLinks.forEach(link => {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            });

            // Modal logic
            const readMoreBtn = cardBody.querySelector(`#read-more-btn-${activity.id}`);
            if (readMoreBtn) {
                readMoreBtn.addEventListener("click", () => {
                let modal = document.getElementById("activityModal");
                if (!modal) {
                    modal = document.createElement("div");
                    modal.className = "modal fade";
                    modal.id = "activityModal";
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
                document.getElementById("activityModalLabel").innerHTML = activity.name;
                let modalBody = `<div class='row'>`;
                if (activity.cover_picture) {
                    modalBody += `<div class='col-md-4 text-center mb-3 mb-md-0'><img src='${activity.cover_picture}' class='img-fluid rounded' alt='cover ${activity.name}'></div>`;
                }
                modalBody += `<div class='col-md-8'>
                    <p class='mb-1'><span class='text-danger'>📍</span> <b>Luogo:</b> <i>${activity.location || ""}</i></p>
                    <p class='mb-1'><b>Inizio:</b> ${formatDateIT(activity.start_date)}</p>
                    <p class='mb-1'><b>Fine:</b> ${formatDateIT(activity.end_date)}</p>
                    <div class='mt-2'>${activity.description || ""}</div>
                    ${activity.url ? `<a href='${activity.url}' class='btn btn-outline-primary mt-3' target='_blank'>Vai all'evento</a>` : ""}
                </div></div>`;
                document.getElementById("activityModalBody").innerHTML = modalBody;
                
                // Force all links in the modal to open in new tabs
                const modalLinks = document.querySelectorAll("#activityModalBody a");
                modalLinks.forEach(link => {
                    link.setAttribute('target', '_blank');
                    link.setAttribute('rel', 'noopener noreferrer');
                });
                
                const bsModal = new bootstrap.Modal(modal);
                
                // Fix per accessibilità: rimuovi aria-hidden quando il modal è mostrato
                modal.addEventListener('shown.bs.modal', function() {
                    modal.removeAttribute('aria-hidden');
                });
                
                // Ripristina aria-hidden quando il modal è nascosto
                modal.addEventListener('hidden.bs.modal', function() {
                    modal.setAttribute('aria-hidden', 'true');
                });
                
                bsModal.show();
                });
            }

            // Marker mappa
            if (activity.latitude && activity.longitude) {
                activitiesWithCoords++;
                const marker = L.marker([activity.latitude, activity.longitude]).addTo(map);
                
                // Crea il contenuto del popup con data, luogo e link all'evento
                const popupContent = `
                    <div style="min-width: 200px;">
                        <h6 class="mb-1"><b>${activity.name}</b></h6>
                        <p class="mb-2 text-dark small">${formatDateIT(activity.start_date)}</p>
                        ${activity.location ? `<p class="mb-2 text-dark small"><span class="text-danger">📍</span> ${activity.location}</p>` : ''}
                        <a href="#event-${activity.id}" class="btn btn-sm btn-outline-primary" onclick="event.preventDefault(); scrollAndHighlightEvent('${activity.id}'); window.location.hash = 'event-${activity.id}';">
                            Vai all'evento
                        </a>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                window.activityMarkers.push(marker);
            }
        });

        if (activitiesWithCoords === 0) {
            const mapNotice = document.createElement("div");
            mapNotice.className = "text-center text-body-secondary p-3";
            mapNotice.innerHTML = "No activities have map coordinates.";
            document.getElementById("map-container").appendChild(mapNotice);
        }
    }

    async function fetchActivities(map) {
        // Check if Sentry is available and has startSpan method
        if (typeof Sentry !== 'undefined' && Sentry.startSpan) {
            return Sentry.startSpan(
                {
                    op: "http.client",
                    name: "GET /api/activities",
                },
                async () => {
                const activitiesList = document.getElementById("activities-list");
                const loadingIndicator = document.getElementById("loading");
                try {
                    const response = await fetch("/api/activities");
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const activities = await response.json();
                    allActivities = activities;
                    // Ordina allActivities per data di inizio crescente
                    allActivities.sort((a, b) => new Date(a.start_date) - new Date(b.start_date));
                    filteredActivities = allActivities.filter((a) => {
                        const now = new Date();
                        return !a.start_date || new Date(a.start_date) >= now;
                    });
                    populateFilters(activities);
                    // Clear loading indicator
                    loadingIndicator.remove();
                    renderCalendar(allActivities);
                    renderActivities(filteredActivities, map);
                    
                    // Abilita il pulsante di condivisione
                    const shareBtn = document.getElementById("share-summary-btn");
                    if (shareBtn) {
                        shareBtn.disabled = false;
                    }

                } catch (error) {
                    loadingIndicator.innerHTML = '<p class="text-center text-danger">Failed to load activities. Please try again later.</p>';
                    console.error("Error fetching activities:", error);
                    if (typeof Sentry !== 'undefined' && Sentry.captureException) {
                        Sentry.captureException(error);
                    }
                }
                });
        } else {
            // Fallback when Sentry is not available
            const activitiesList = document.getElementById("activities-list");
            const loadingIndicator = document.getElementById("loading");
            try {
                const response = await fetch("/api/activities");
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const activities = await response.json();
                allActivities = activities;
                // Ordina allActivities per data di inizio crescente
                allActivities.sort((a, b) => new Date(a.start_date) - new Date(b.start_date));
                filteredActivities = allActivities.filter((a) => {
                    const now = new Date();
                    return !a.start_date || new Date(a.start_date) >= now;
                });
                populateFilters(activities);
                // Clear loading indicator
                loadingIndicator.remove();
                renderCalendar(allActivities);
                renderActivities(filteredActivities, map);

                // Abilita il pulsante di condivisione
                const shareBtn = document.getElementById("share-summary-btn");
                if (shareBtn) {
                    shareBtn.disabled = false;
                }

            } catch (error) {
                loadingIndicator.innerHTML = '<p class="text-center text-danger">Failed to load activities. Please try again later.</p>';
                console.error("Error fetching activities:", error);
            }
        }
    }

    // Funzione per generare il riassunto per WhatsApp
    function generateWhatsAppSummary(activities) {
        const now = new Date();
        const futureEvents = activities
            .filter(a => !a.start_date || new Date(a.start_date) >= now)
            .sort((a, b) => new Date(a.start_date) - new Date(b.start_date));

        if (futureEvents.length === 0) {
            return "Nessun evento futuro da mostrare.";
        }

        const eventsByMonthThenDay = futureEvents.reduce((acc, event) => {
            const startDate = new Date(event.start_date);
            const month = startDate.toLocaleString('it-IT', { month: 'long', year: 'numeric' });
            const dayKey = startDate.toDateString();

            if (!acc[month]) {
                acc[month] = {};
            }
            if (!acc[month][dayKey]) {
                acc[month][dayKey] = [];
            }
            acc[month][dayKey].push(event);
            return acc;
        }, {});

        let summary = "_*Eventi Round Table Italia*_\n\n";

        for (const month in eventsByMonthThenDay) {
            summary += `*${month.toUpperCase()}*\n\n`;

            for (const day in eventsByMonthThenDay[month]) {
                const eventsOnDay = eventsByMonthThenDay[month][day];
                const firstEventDate = new Date(eventsOnDay[0].start_date);

                const dateFormatOptions = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
                const dateString = firstEventDate.toLocaleDateString('it-IT', dateFormatOptions);
                const capitalizedDate = dateString.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');

                summary += `*${capitalizedDate}*\n\n`;

                eventsOnDay.forEach(event => {
                    const startDate = new Date(event.start_date);
                    const endDate = event.end_date ? new Date(event.end_date) : null;

                    summary += `- ${event.name}`;

                    const details = [];
                    if (event.api_endpoint_description) {
                        details.push(event.api_endpoint_description);
                    }
                    if (event.api_endpoint_area) {
                        details.push(event.api_endpoint_area);
                    }
                    if (details.length > 0) {
                        summary += ` (${details.join(' - ')})`;
                    }
                    summary += `\n`;

                    if (endDate && startDate.toDateString() !== endDate.toDateString()) {
                        summary += `  _fino a ${endDate.toLocaleString('it-IT', { weekday: 'long', day: 'numeric' })}_\n`;
                    }

                    if (event.location) {
                        summary += `📍 ${event.location}\n`;
                    }
                    
                    summary += `\n`;
                });
            }
        }

        return summary.trimEnd();
    }

    // Gestione del modal di riepilogo WhatsApp
    const whatsAppModal = document.getElementById('whatsappSummaryModal');
    if (whatsAppModal) {
        whatsAppModal.addEventListener('show.bs.modal', function () {
            const summaryContent = document.getElementById('whatsapp-summary-content');
            if (summaryContent) {
                summaryContent.textContent = generateWhatsAppSummary(allActivities);
            }
        });

        const copyBtn = document.getElementById('copy-summary-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                const summaryText = document.getElementById('whatsapp-summary-content').textContent;
                navigator.clipboard.writeText(summaryText).then(() => {
                    const originalText = copyBtn.textContent;
                    copyBtn.textContent = 'Copiato!';
                    copyBtn.classList.add('btn-success');
                    setTimeout(() => {
                        copyBtn.textContent = originalText;
                        copyBtn.classList.remove('btn-success');
                    }, 2000);
                }).catch(err => {
                    console.error('Errore durante la copia:', err);
                    alert('Impossibile copiare il testo.');
                });
            });
        }
    }

    // Funzione per formattare la data in italiano
    function formatDateIT(dateString) {
        if (!dateString) return "";
        return new Date(dateString).toLocaleString("it-IT", {
            year: "numeric",
            month: "long",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    }

    // Funzione unica per scroll, highlight e aggiornamento hash
    function scrollAndHighlightEvent(eventId) {
        const anchor = document.getElementById(`event-${eventId}`);
        if (anchor) {
            // Rimuovi eventuali highlight precedenti
            document.querySelectorAll('.highlight-event').forEach(el => el.classList.remove('highlight-event'));
            
            // Crea un observer per aspettare che l'elemento sia visibile
            const observer = new IntersectionObserver((entries) => {
                // Questo codice viene eseguito solo quando la card entra in vista
                if (entries[0].isIntersecting) {
                    anchor.classList.add('highlight-event');
                    setTimeout(() => {
                        anchor.classList.remove('highlight-event');
                    }, 700); // Durata dell'animazione
                    
                    // Smettiamo di osservare, il nostro compito è finito
                    observer.unobserve(anchor);
                }
            }, { threshold: 0.9 }); // Si attiva quando il 90% della card è visibile

            // Inizia a osservare la card
            observer.observe(anchor);

            // Avvia lo scroll
            anchor.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Aggiorna sempre l'hash (anche se è già quello)
            if (window.location.hash !== `#event-${eventId}`) {
                window.location.hash = `event-${eventId}`;
            } else {
                history.replaceState(null, '', `#event-${eventId}`);
            }
        }
    }
    window.scrollAndHighlightEvent = scrollAndHighlightEvent;

    // Funzione per renderizzare il calendario mensile con evidenziazione eventi
    function renderCalendar(activities) {
        const calendarEl = document.getElementById("calendar");
        if (!calendarEl) return;

        // Mappa le attività in eventi per FullCalendar
        const events = activities.map((a) => ({
            id: a.id,
            title: a.name,
            start: a.start_date,
            end: a.end_date,
            allDay: true,
        }));

        // Distruggi il calendario precedente se esiste
        if (window.fcInstance) {
            window.fcInstance.destroy();
        }

        window.fcInstance = new FullCalendar.Calendar(calendarEl, {
            initialView: "dayGridMonth",
            themeSystem: "bootstrap5",
            locale: "it",
            events: events,
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "",
            },
            eventClick: function(info) {
                scrollAndHighlightEvent(info.event.id);
            },
        });
        window.fcInstance.render();
    }
})();
