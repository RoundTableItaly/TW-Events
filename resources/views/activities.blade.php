<!doctype html>
<html lang="en" data-bs-theme="auto">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Round Table Italia Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
  </head>
  <body class="d-flex flex-column h-100">

    <header>
      <!-- Fixed navbar -->
      <nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
        <div class="container-fluid">
          <a class="navbar-brand text-truncate" href="#" style="max-width: 60vw; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Round Table Italia Events Map</a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="navbarCollapse">
            <ul class="navbar-nav me-auto mb-2 mb-md-0">
              <li class="nav-item">
                <!-- <a class="nav-link active" aria-current="page" href="#">Home</a> -->
              </li>
            </ul>
            <ul class="navbar-nav mb-2 mb-md-0">
                <li class="nav-item dropdown">
                    <button class="btn btn-link nav-link dropdown-toggle" id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" data-bs-display="static">
                        <i class="bi bi-circle-half" id="bd-theme-icon"></i>
                        <span id="bd-theme-text">Toggle theme</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="bd-theme-text">
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
    <main class="flex-shrink-0">
      <div class="container">        
        <div class="row mt-4">
          <div class="col-12 mb-3">
            <form class="row g-2 align-items-center" id="filters-form">
              <div class="col-auto">
                <label for="filter-area" class="form-label mb-0">Zona</label>
                <select id="filter-area" class="form-select form-select-sm" style="min-width: 120px;">
                  <option value="">Tutte</option>
                </select>
              </div>
              <div class="col-auto">
                <label for="filter-description" class="form-label mb-0">Tavola</label>
                <select id="filter-description" class="form-select form-select-sm" style="min-width: 120px;">
                  <option value="">Tutte</option>
                </select>
              </div>
            </form>
          </div>
          <!-- Activities Column -->
          <div class="col-lg-8">
            <div id="activities-list" class="row">
              <!-- Activities will be loaded here by JavaScript -->
              <div id="loading" class="text-center text-body-secondary py-5">
                <div class="spinner-border" role="status">
                  <span class="visually-hidden">Caricamento...</span>
                </div>
                <p class="mt-2">Caricamento eventi...</p>
              </div>
            </div>
          </div>
          <!-- Map Column -->
          <div class="col-lg-4">
            <div id="map-container" class="sticky-top" style="top: 80px; height: 500px;"></div>
          </div>
        </div>

      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.10/dist/purify.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    </body>
</html> 