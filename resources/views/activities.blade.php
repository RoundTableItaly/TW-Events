<!doctype html>
<html lang="en" data-bs-theme="auto">
  <head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-455DX8QQGV" onerror="console.warn('Google Analytics failed to load')"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-455DX8QQGV', {
        // Configurazione per ridurre errori
        send_page_view: true
      });
    </script>
    <!-- Sentry Browser SDK - Complete Bundle with Performance Monitoring -->
    <script
      src="https://js.sentry-cdn.com/bundle.tracing.min.js"
      crossorigin="anonymous"
    ></script>
    <script>
      Sentry.init({
        dsn: "{{ env('SENTRY_DSN') }}",
        
        // Complete performance monitoring
        tracesSampleRate: 1.0,
        profilesSampleRate: 1.0,
        
        // Complete integrations for maximum visibility
        integrations: [
          new Sentry.BrowserTracing(),
          new Sentry.Replay({
            // Capture 10% of all sessions
            sessionSampleRate: 0.1,
            // Capture 100% of sessions with an error
            errorSampleRate: 1.0,
            // Capture console logs, network requests, and DOM events
            maskAllText: false,
            blockAllMedia: false,
          }),
          new Sentry.Feedback({
            // Show feedback widget on errors
            autoInject: true,
          }),
        ],
        
        // Environment and release tracking
        environment: "{{ env('APP_ENV', 'production') }}",
        release: "{{ env('SENTRY_RELEASE', '1.0.0') }}",
        
        // Enhanced error capture
        beforeSend(event, hint) {
          // Log to console for debugging
          console.log('Sentry event:', event);
          return event;
        },
        
        // Capture unhandled promise rejections
        captureUnhandledRejections: true,
        
        // Enhanced breadcrumbs
        beforeBreadcrumb(breadcrumb, hint) {
          // Capture all breadcrumbs for complete visibility
          return breadcrumb;
        },
      });
      
      // Test Sentry integration
      console.log('Sentry initialized with complete monitoring');
      
      // Test functions for manual testing
      window.testSentry = function() {
        Sentry.captureException(new Error('Test error from button click'));
      };
      
      window.testSentryTransaction = function() {
        Sentry.startSpan({
          op: 'test',
          name: 'Manual Test Transaction'
        }, () => {
          console.log('Test transaction completed');
        });
      };
      
      window.testSentryReplay = function() {
        Sentry.showReportDialog();
      };
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Round Table Italia Events</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" integrity="sha256-zRgmWB5PK4CvTx4FiXsxbHaYRBBjz/rvu97sOC7kzXI=" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
  </head>
  <body class="d-flex flex-column h-100">

    <header>
      <!-- Fixed navbar -->
      <nav class="navbar navbar-expand-md fixed-top bg-body-tertiary">
        <div class="container-fluid">
          <a class="navbar-brand d-flex align-items-center" href="#" style="max-width: 60vw;">
            <img src="{{ asset('rt-italy_logo-2023-horizontal.svg') }}" alt="Round Table Italia" class="navbar-logo light-logo" style="height: 40px; width: auto;">
            <img src="{{ asset('rt-italy_logo-2023-horizontal_neg.svg') }}" alt="Round Table Italia" class="navbar-logo dark-logo" style="height: 40px; width: auto; display: none;">
            <div class="vr mx-2"></div>
            <span class="text-truncate" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Events</span>
          </a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="navbarCollapse">
            <ul class="navbar-nav me-auto mb-2 mb-md-0">
              <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#collapseMap" aria-expanded="false" aria-controls="collapseMap">
                  <i class="bi bi-geo-alt"></i> Mappa
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#collapseCalendar" aria-expanded="false" aria-controls="collapseCalendar">
                  <i class="bi bi-calendar3"></i> Calendario
                </a>
              </li>
            </ul>
            <ul class="navbar-nav mb-2 mb-md-0">
                <li class="nav-item me-2">
                    <button class="btn btn-link nav-link" id="share-summary-btn" type="button" aria-expanded="false" data-bs-toggle="modal" data-bs-target="#whatsappSummaryModal" disabled title="Genera riepilogo per WhatsApp">
                        <i class="bi bi-whatsapp"></i> Condividi
                    </button>
                </li>
                <li class="nav-item me-2">
                    <a class="btn btn-link nav-link" href="{{ route('activities.ics') }}" title="Download ICS" download>
                        <i class="bi bi-calendar-plus"></i> ICS
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <button class="btn btn-link nav-link" id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" data-bs-display="static">
                        <i class="bi bi-circle-half" id="bd-theme-icon"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="bd-theme">
                        <li>
                            <button type="button" class="dropdown-item" data-bs-theme-value="light">
                                <i class="bi bi-sun-fill me-2"></i>Light
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item" data-bs-theme-value="dark">
                                <i class="bi bi-moon-stars-fill me-2"></i>Dark
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item" data-bs-theme-value="auto">
                                <i class="bi bi-circle-half me-2"></i>Auto
                            </button>
                        </li>
                    </ul>
                </li>
            </ul>
          </div>
        </div>
      </nav>
    </header>

    <!-- Begin page content -->
    <main class="main-flex-container flex-column">
      <div class="filters-flex mb-3">
        <div class="card">
          <div class="card-body">
            <form class="d-flex flex-wrap align-items-end gap-2" id="filters-form">
              <div class="mb-2">
                <label for="filter-area" class="form-label mb-0">Zona</label>
                <select id="filter-area" class="form-select form-select-sm" style="min-width: 120px;">
                  <option value="">Tutte</option>
                </select>
              </div>
              <div class="mb-2">
                <label for="filter-description" class="form-label mb-0">Tavola</label>
                <select id="filter-description" class="form-select form-select-sm" style="min-width: 120px;">
                  <option value="">Tutte</option>
                </select>
              </div>
              <div class="mb-2 align-self-end" id="show-past-events-container">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="show-past-events-btn">
                  Mostra eventi passati
                </button>
              </div>

            </form>
          </div>
        </div>
      </div>
      <div class="map-flex">
        <div class="collapse" id="collapseMap">
          <div id="map-container" class="mb-3"></div>
        </div>
      </div>
      <div class="calendar-flex">
        <div class="collapse" id="collapseCalendar">
          <div id="calendar" class="mb-3"></div>
        </div>
      </div>
      <div id="activities-list" class="activities-flex">
        <!-- Activities will be loaded here by JavaScript -->
        <div id="loading" class="text-center text-body-secondary py-5">
          <div class="spinner-border" role="status">
            <span class="visually-hidden">Caricamento...</span>
          </div>
          <p class="mt-2">Caricamento eventi...</p>
        </div>
      </div>
    </main>

    <!-- WhatsApp Summary Modal -->
    <div class="modal fade" id="whatsappSummaryModal" tabindex="-1" aria-labelledby="whatsappSummaryModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="whatsappSummaryModalLabel">Riassunto Eventi Futuri per WhatsApp</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Copia il testo seguente e incollalo nella tua chat di WhatsApp.</p>
            <div id="whatsapp-summary-content">
              <!-- Summary will be injected here -->
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            <button type="button" class="btn btn-primary" id="copy-summary-btn">Copia Testo</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.js" integrity="sha256-tQ9c3dc1t0j9EV2Itwqx1ZK0qjrLayj0+l/lSEgU5ZM=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.18/index.global.min.js" integrity="sha256-twlJ8c4S3MT/5wF9SMbpH5ml++7XY4HmSkdZdx/scpw=" crossorigin="anonymous"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    </body>
</html> 