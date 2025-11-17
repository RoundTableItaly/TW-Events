<!doctype html>
<html lang="en" data-bs-theme="auto">
  <head>
    <!-- Google tag (gtag.js) -->
    @if(env('APP_DEBUG') === false)
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-455DX8QQGV" onerror="console.warn('Google Analytics failed to load')"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-455DX8QQGV', {
        send_page_view: true
      });
    </script>
    @endif
    @if(env('APP_DEBUG') === false)
    <!-- Sentry Browser SDK - Loader Script -->
    <script src="{{ env('SENTRY_FRONTEND_LOADER_URL') }}" crossorigin="anonymous"></script>
    <script>
      window.Sentry = {
        onLoad: function() {
          Sentry.init({
            dsn: "{{ env('SENTRY_FRONTEND_DSN') }}",
            release: "{{ 'TW-Events@' . env('SENTRY_APP_VERSION', '1.0.0') }}",
            integrations: [
              Sentry.browserTracingIntegration(),
              Sentry.replayIntegration({
                maskAllText: false,
                blockAllMedia: false,
              }),
            ],
            tracesSampleRate: 1.0,
            replaysSessionSampleRate: 0.1,
            replaysOnErrorSampleRate: 1.0,
            tracePropagationTargets: ["localhost", /^https:\/\/events\.roundtable\.it\/api/],
          });
        }
      };
    </script>
    @endif
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Round Table Italia Events - Statistiche</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" integrity="sha256-zRgmWB5PK4CvTx4FiXsxbHaYRBBjz/rvu97sOC7kzXI=" crossorigin="anonymous">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
  </head>
  <body class="d-flex flex-column h-100">

    <header>
      <!-- Fixed navbar -->
      <nav class="navbar navbar-expand-md fixed-top bg-body-tertiary">
        <div class="container-fluid">
          <a class="navbar-brand d-flex align-items-center" href="{{ route('activities.index') }}" style="max-width: 60vw;">
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
                <a class="nav-link" href="{{ route('activities.index') }}">
                  <i class="bi bi-calendar3"></i> Eventi
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link active" href="#">
                  <i class="bi bi-graph-up"></i> Statistiche
                </a>
              </li>
            </ul>
            <ul class="navbar-nav mb-2 mb-md-0">
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
      <div class="container-fluid py-4">
        <h1 class="h3 mb-4">
          <i class="bi bi-graph-up"></i> Statistiche Eventi
        </h1>

        <!-- Loading indicator -->
        <div id="loading" class="text-center text-body-secondary py-5">
          <div class="spinner-border" role="status">
            <span class="visually-hidden">Caricamento statistiche...</span>
          </div>
          <p class="mt-2">Caricamento statistiche...</p>
        </div>

        <!-- Error message -->
        <div id="error-message" class="alert alert-danger" role="alert" style="display: none;">
          <i class="bi bi-exclamation-triangle"></i>
          <span id="error-text">Errore nel caricamento delle statistiche. Riprova più tardi.</span>
        </div>

        <!-- Statistics content -->
        <div id="statistics-content" style="display: none;">
          <!-- Overview KPIs -->
          <div class="row mb-4" id="overview-cards">
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-primary text-white h-100">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="card-title">Totale Eventi</h6>
                      <h3 class="mb-0">{{ $statistics['total_events'] }}</h3>
                    </div>
                    <div class="fs-1 opacity-50">
                      <i class="bi bi-calendar-event"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-success text-white h-100">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="card-title">Eventi Futuri</h6>
                      <h3 class="mb-0">{{ $statistics['future_events'] }}</h3>
                    </div>
                    <div class="fs-1 opacity-50">
                      <i class="bi bi-arrow-right-circle"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-secondary text-white h-100">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="card-title">Eventi Passati</h6>
                      <h3 class="mb-0">{{ $statistics['past_events'] }}</h3>
                    </div>
                    <div class="fs-1 opacity-50">
                      <i class="bi bi-arrow-left-circle"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-danger text-white h-100">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="card-title">Eventi Cancellati</h6>
                      <h3 class="mb-0">{{ $statistics['canceled_events'] }}</h3>
                    </div>
                    <div class="fs-1 opacity-50">
                      <i class="bi bi-x-circle"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-info text-white h-100">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="card-title">Eventi del Mese</h6>
                      <h3 class="mb-0">{{ $statistics['current_month_events'] }}</h3>
                    </div>
                    <div class="fs-1 opacity-50">
                      <i class="bi bi-calendar"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="card-title">Anno Sociale Corrente</h6>
                      <h3 class="mb-0">{{ $statistics['current_social_year_events'] }}</h3>
                      <small class="opacity-75">{{ $statistics['current_social_year_start'] }} - {{ $statistics['current_social_year_end'] }}</small>
                    </div>
                    <div class="fs-1 opacity-50">
                      <i class="bi bi-calendar-range"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-3">
              <div class="card bg-light text-dark h-100">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="card-title">Anno Sociale Precedente</h6>
                      <h3 class="mb-0">{{ $statistics['previous_social_year_events'] }}</h3>
                      <small class="opacity-75">{{ $statistics['previous_social_year_start'] }} - {{ $statistics['previous_social_year_end'] }}</small>
                    </div>
                    <div class="fs-1 opacity-50">
                      <i class="bi bi-calendar-range"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Charts Row 1 - Top Tables and Zones -->
          <div class="row mb-4">
            <div class="col-lg-6 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-bar-chart"></i> Top Tavole per Eventi
                  </h5>
                </div>
                <div class="card-body">
                  <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="topTablesChart"></canvas>
                  </div>
                  <div id="topTablesChart-empty" class="text-center text-muted py-4" style="display: none;">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Nessun dato disponibile</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-6 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-pie-chart"></i> Eventi per Zona
                  </h5>
                </div>
                <div class="card-body">
                  <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="zonesChart"></canvas>
                  </div>
                  <div id="zonesChart-empty" class="text-center text-muted py-4" style="display: none;">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Nessun dato disponibile</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Charts Row 2 - Three small charts -->
          <div class="row mb-4">
            <div class="col-lg-4 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-calendar-range"></i> Eventi Multi-giornata vs Singola Giornata
                  </h5>
                </div>
                <div class="card-body">
                  <div class="d-flex flex-column align-items-center mb-3">
                    <div class="mb-2">
                      <span class="badge bg-primary fs-6 me-2">{{ $statistics['multi_day_events'] }}</span>
                      <span>Multi-giornata ({{ $statistics['multi_day_percentage'] }}%)</span>
                    </div>
                    <div>
                      <span class="badge bg-info fs-6 me-2">{{ $statistics['single_day_events'] }}</span>
                      <span>Singola giornata ({{ $statistics['single_day_percentage'] }}%)</span>
                    </div>
                  </div>
                  <div class="chart-container" style="position: relative; height: 200px;">
                    <canvas id="multiDayChart"></canvas>
                  </div>
                  <div id="multiDayChart-empty" class="text-center text-muted py-4" style="display: none;">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Nessun dato disponibile</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-calendar-week"></i> Giorno della Settimana più Comune
                  </h5>
                </div>
                <div class="card-body">
                  @if(isset($statistics['day_of_week_distribution']) && $statistics['day_of_week_distribution']->count() > 0)
                  @php
                    $mostCommon = $statistics['day_of_week_distribution']->sortByDesc('event_count')->first();
                  @endphp
                  @if($mostCommon)
                  <div class="text-center mb-3">
                    <h3 class="mb-0">{{ $mostCommon->day_name }}</h3>
                    <p class="text-muted mb-0">{{ $mostCommon->event_count }} eventi</p>
                  </div>
                  @endif
                  @endif
                  <div class="chart-container" style="position: relative; height: 200px;">
                    <canvas id="dayOfWeekChart"></canvas>
                  </div>
                  <div id="dayOfWeekChart-empty" class="text-center text-muted py-4" style="display: none;">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Nessun dato disponibile</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-tags"></i> Tipologia Eventi
                  </h5>
                </div>
                <div class="card-body">
                  <div class="chart-container" style="position: relative; height: 200px;">
                    <canvas id="typesChart"></canvas>
                  </div>
                  <div id="typesChart-empty" class="text-center text-muted py-4" style="display: none;">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Nessun dato disponibile</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Charts Row 3 - Monthly Distribution (full width) -->
          <div class="row mb-4">
            <div class="col-12 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up-arrow"></i> Distribuzione Mensile Eventi
                  </h5>
                </div>
                <div class="card-body">
                  <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="monthlyChart"></canvas>
                  </div>
                  <div id="monthlyChart-empty" class="text-center text-muted py-4" style="display: none;">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Nessun dato disponibile</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Charts Row 4 - Days from Publication (full width) -->
          <div class="row mb-4">
            <div class="col-12 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-bell"></i> Giorni da Pubblicazione a Evento
                  </h5>
                </div>
                <div class="card-body">
                  <p class="text-secondary small mb-3">
                    <i class="bi bi-info-circle"></i> Questo grafico mostra quanti giorni prima della data di inizio dell'evento è stato pubblicato (creato) l'evento.
                  </p>
                  <div class="mb-3">
                    <label for="maxDaysFilter" class="form-label small">
                      Giorni massimi: <span id="maxDaysValue">180</span>
                    </label>
                    <input type="range" class="form-range" id="maxDaysFilter" min="30" max="365" value="180" step="5">
                    <small class="text-secondary d-block mt-1">Filtra eventi pubblicati oltre questo numero di giorni prima della data di inizio</small>
                  </div>
                  <div id="bellCurveStats" class="text-center mb-3" style="display: none;">
                    <h6 class="mb-1">Media: <span id="meanDaysValue">-</span> giorni prima della data di inizio</h6>
                    <small class="text-secondary">Deviazione standard: <span id="stdDevValue">-</span> giorni</small>
                    <br>
                    <small class="text-secondary">Eventi esclusi: <span id="excludedEventsCount">0</span></small>
                  </div>
                  <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="bellCurveChart"></canvas>
                  </div>
                  <div id="bellCurveChart-empty" class="text-center text-muted py-4" style="display: none;">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Nessun dato disponibile</p>
                  </div>
                  <div id="bellCurveEventsDetails" class="mt-3" style="display: none;">
                    <div class="card border-primary">
                      <div class="card-header bg-primary text-white">
                        <h6 class="card-title mb-0">
                          <i class="bi bi-info-circle"></i> Eventi pubblicati <span id="selectedDaysValue">-</span> giorni prima della data di inizio
                        </h6>
                      </div>
                      <div class="card-body">
                        <div id="eventsList" class="list-group list-group-flush">
                          <!-- Events will be populated here -->
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Best Organized Tables Row -->
          <div class="row mb-4">
            <div class="col-12 mb-3">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-trophy"></i> Tavole meglio Organizzate
                  </h5>
                </div>
                <div class="card-body">
                  <p class="text-secondary small mb-3">
                    <i class="bi bi-info-circle"></i> Classifica delle tavole che pubblicano gli eventi con maggiore anticipo (media giorni tra pubblicazione e data evento). Mostra solo tavole con almeno 5 eventi.
                  </p>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead>
                        <tr>
                          <th style="width: 50px;">#</th>
                          <th>Tavola</th>
                          <th class="text-end">Media Giorni</th>
                          <th class="text-end">Numero Eventi</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($statistics['best_organized_tables'] as $index => $table)
                          <tr>
                            <td>
                              <span class="badge bg-{{ $index < 3 ? 'warning' : 'secondary' }} text-dark rounded-pill">
                                {{ $index + 1 }}
                              </span>
                            </td>
                            <td><strong>{{ $table['table_name'] }}</strong></td>
                            <td class="text-end">
                              <span class="badge bg-success rounded-pill">{{ $table['avg_days'] }} giorni</span>
                            </td>
                            <td class="text-end">
                              <span class="badge bg-primary rounded-pill">{{ $table['event_count'] }}</span>
                            </td>
                          </tr>
                        @empty
                          <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                              <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                              Nessun dato disponibile
                            </td>
                          </tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tables Row -->
          <div class="row mb-4">
            <div class="col-lg-6 mb-3">
              <div class="card">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-trophy"></i> Tavole con più Eventi
                  </h5>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-hover" id="topTablesTable">
                      <thead>
                        <tr>
                          <th>Tavola</th>
                          <th class="text-end">Eventi</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($statistics['top_tables'] as $table)
                          <tr>
                            <td>{{ $table->table_name }}</td>
                            <td class="text-end">
                              <span class="badge bg-primary rounded-pill">{{ $table->event_count }}</span>
                            </td>
                          </tr>
                        @empty
                          <tr>
                            <td colspan="2" class="text-center text-muted">Nessun dato disponibile</td>
                          </tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-6 mb-3">
              <div class="card">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-geo-alt"></i> Eventi per Zona
                  </h5>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-hover" id="zonesTable">
                      <thead>
                        <tr>
                          <th>Zona</th>
                          <th class="text-end">Eventi</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($statistics['events_by_zone'] as $zone)
                          <tr>
                            <td>{{ $zone->zone }}</td>
                            <td class="text-end">
                              <span class="badge bg-info rounded-pill">{{ $zone->event_count }}</span>
                            </td>
                          </tr>
                        @empty
                          <tr>
                            <td colspan="2" class="text-center text-muted">Nessun dato disponibile</td>
                          </tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tavole senza Attività Row -->
          <div class="row mb-4">
            <div class="col-12 mb-3">
              <div class="card h-100 border-warning">
                <div class="card-header bg-warning text-dark">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-exclamation-triangle"></i> Tavole senza Attività
                  </h5>
                </div>
                <div class="card-body">
                  <p class="text-secondary small mb-3">
                    <i class="bi bi-info-circle"></i> Tavole che pubblicano pochi o nessun evento (solo tavole locali, escluse RT Italia e le zone).
                  </p>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead>
                        <tr>
                          <th>Tavola</th>
                          <th class="text-end">Numero Eventi</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($statistics['tables_without_activities'] as $table)
                          <tr class="{{ $table['event_count'] == 0 ? 'table-danger' : ($table['event_count'] <= 2 ? 'table-warning' : '') }}">
                            <td>
                              <strong>{{ $table['table_name'] }}</strong>
                              @if($table['area'])
                                <br><small class="text-secondary"><i class="bi bi-geo-alt"></i> {{ $table['area'] }}</small>
                              @endif
                            </td>
                            <td class="text-end">
                              @if($table['event_count'] == 0)
                                <span class="badge bg-danger rounded-pill">0 eventi</span>
                              @elseif($table['event_count'] <= 2)
                                <span class="badge bg-warning text-dark rounded-pill">{{ $table['event_count'] }} eventi</span>
                              @else
                                <span class="badge bg-info rounded-pill">{{ $table['event_count'] }} eventi</span>
                              @endif
                            </td>
                          </tr>
                        @empty
                          <tr>
                            <td colspan="2" class="text-center text-muted py-4">
                              <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                              Nessun dato disponibile
                            </td>
                          </tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tavole senza Attività per Zona Row -->
          <div class="row mb-4">
            <div class="col-12 mb-3">
              <div class="card h-100 border-warning">
                <div class="card-header bg-warning text-dark">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-exclamation-triangle"></i> Tavole senza Attività per Zona
                  </h5>
                </div>
                <div class="card-body">
                  <p class="text-secondary small mb-3">
                    <i class="bi bi-info-circle"></i> Tavole che pubblicano pochi o nessun evento, suddivise per zona.
                  </p>
                  @forelse($statistics['tables_without_activities_by_zone'] as $zoneData)
                    <div class="mb-4">
                      <h6 class="mb-3">
                        <i class="bi bi-geo-alt"></i> {{ $zoneData['zone'] }}
                        <span class="badge bg-secondary ms-2">{{ $zoneData['total_tables'] }} tavole</span>
                        @if($zoneData['tables_with_no_events'] > 0)
                          <span class="badge bg-danger ms-2">{{ $zoneData['tables_with_no_events'] }} senza eventi</span>
                        @endif
                      </h6>
                      <div class="table-responsive">
                        <table class="table table-sm table-hover">
                          <thead>
                            <tr>
                              <th>Tavola</th>
                              <th class="text-end">Numero Eventi</th>
                            </tr>
                          </thead>
                          <tbody>
                            @foreach($zoneData['tables'] as $table)
                              <tr class="{{ $table['event_count'] == 0 ? 'table-danger' : ($table['event_count'] <= 2 ? 'table-warning' : '') }}">
                                <td><strong>{{ $table['table_name'] }}</strong></td>
                                <td class="text-end">
                                  @if($table['event_count'] == 0)
                                    <span class="badge bg-danger rounded-pill">0 eventi</span>
                                  @elseif($table['event_count'] <= 2)
                                    <span class="badge bg-warning text-dark rounded-pill">{{ $table['event_count'] }} eventi</span>
                                  @else
                                    <span class="badge bg-info rounded-pill">{{ $table['event_count'] }} eventi</span>
                                  @endif
                                </td>
                              </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </div>
                    </div>
                  @empty
                    <div class="text-center text-muted py-4">
                      <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                      Nessun dato disponibile
                    </div>
                  @endforelse
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.js" integrity="sha256-tQ9c3dc1t0j9EV2Itwqx1ZK0qjrLayj0+l/lSEgU5ZM=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js" integrity="sha256-SERKgtTty1vsDxll+qzd4Y2cF9swY9BCq62i9wXJ9Uo=" crossorigin="anonymous"></script>
    <script src="{{ asset('js/statistics.js') }}"></script>
  </body>
</html>
