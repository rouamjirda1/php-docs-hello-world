<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: dashboard.php");
    exit();
}

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

// Function to categorize cities by region
function getRegion($city) {
    $regions = [
        'North Africa' => ['Tunis', 'Cairo', 'Casablanca', 'Marrakech', 'Algiers', 'Oran', 'Alexandria'],
        'Europe - Western' => ['Paris', 'London', 'Madrid', 'Barcelona', 'Amsterdam', 'Brussels', 'Zurich', 'Geneva'],
        'Europe - Central' => ['Frankfurt', 'Munich', 'Berlin', 'Vienna', 'Rome', 'Milan'],
        'Middle East' => ['Dubai', 'Doha', 'Riyadh', 'Jeddah', 'Kuwait', 'Istanbul', 'Tel Aviv'],
        'Asia - East' => ['Tokyo', 'Seoul', 'Shanghai', 'Hong Kong', 'Singapore', 'Bangkok', 'Kuala Lumpur'],
        'Asia - South' => ['Mumbai'],
        'North America' => ['New York', 'Los Angeles', 'Miami', 'Chicago', 'San Francisco', 'Boston', 'Toronto'],
        'Oceania' => ['Sydney', 'Melbourne'],
        'Europe - Eastern' => ['Moscow', 'Athens'],
        'Africa - Sub-Saharan' => ['Johannesburg']
    ];
    
    foreach ($regions as $region => $cities) {
        if (in_array($city, $cities)) {
            return $region;
        }
    }
    return 'Other Destinations';
}

// Get all unique origins and destinations for dropdowns
$origins_query = "SELECT DISTINCT origin, origin_code FROM routes WHERE status = 'active' ORDER BY origin";
$origins = $conn->query($origins_query);

$destinations_query = "SELECT DISTINCT destination, destination_code FROM routes WHERE status = 'active' ORDER BY destination";
$destinations = $conn->query($destinations_query);

/// Search functionality
$flights = null;
$search_performed = false;

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search'])) {
    $search_performed = true;
    
    $origin = isset($_GET['origin']) ? $_GET['origin'] : '';
    $destination = isset($_GET['destination']) ? $_GET['destination'] : '';
    $departure_date = isset($_GET['departure_date']) ? $_GET['departure_date'] : '';
    $seat_class = isset($_GET['seat_class']) ? $_GET['seat_class'] : '';
    
    // Build search query
    $query = "SELECT f.*, a.name as airline_name, a.code as airline_code, a.logo_url,
              r.origin, r.destination, r.origin_code, r.destination_code, r.duration,
              CASE 
                WHEN ? = 'economy' THEN f.economy_price
                WHEN ? = 'business' THEN f.business_price
                WHEN ? = 'first_class' THEN f.first_class_price
                ELSE f.economy_price
              END as selected_price,
              CASE 
                WHEN ? = 'economy' THEN f.available_economy
                WHEN ? = 'business' THEN f.available_business
                WHEN ? = 'first_class' THEN f.available_first_class
                ELSE f.available_economy
              END as available_seats
              FROM flights f
              JOIN airlines a ON f.airline_id = a.id
              JOIN routes r ON f.route_id = r.id
              WHERE f.status = 'scheduled'
              AND a.status = 'active'";
    
    $params = [$seat_class, $seat_class, $seat_class, $seat_class, $seat_class, $seat_class];
    $types = "ssssss";
    
    if (!empty($origin)) {
        $query .= " AND r.origin_code = ?";
        $params[] = $origin;
        $types .= "s";
    }
    
    if (!empty($destination)) {
        $query .= " AND r.destination_code = ?";
        $params[] = $destination;
        $types .= "s";
    }
    
    if (!empty($departure_date)) {
        $query .= " AND DATE(f.departure_time) = ?";
        $params[] = $departure_date;
        $types .= "s";
    }
    
    $query .= " ORDER BY f.departure_time ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $flights = $stmt->get_result();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Flights - AirInsight</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/search.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-plane"></i>
            <h2>AirInsight</h2>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="search_flights.php" class="nav-item active">
                <i class="fas fa-search"></i>
                <span>Search Flights</span>
            </a>
            <a href="my_bookings.php" class="nav-item">
                <i class="fas fa-ticket-alt"></i>
                <span>My Bookings</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="reviews.php" class="nav-item">
                <i class="fas fa-star"></i>
                <span>Reviews</span>
            </a>
            <a href="../logout.php" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>Search Flights</h1>
            </div>
            <div class="header-right">
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </button>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=0066cc&color=fff" alt="User">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="user-role">Passenger</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Search Form -->
        <section class="search-section">
            <div class="search-form-container">
                <h2><i class="fas fa-plane-departure"></i> Find Your Perfect Flight</h2>
                <form method="GET" action="search_flights.php" class="flight-search-form">
                    <input type="hidden" name="search" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="origin"><i class="fas fa-map-marker-alt"></i> From</label>
                            <select id="origin" name="origin" required class="searchable-select">
                                <option value="">Select Origin City</option>
                                <?php 
                                $origins->data_seek(0);
                                $cities_by_region = [];
                                while($row = $origins->fetch_assoc()) {
                                    // Group cities by region
                                    $region = getRegion($row['origin']);
                                    if (!isset($cities_by_region[$region])) {
                                        $cities_by_region[$region] = [];
                                    }
                                    $cities_by_region[$region][] = $row;
                                }
                                
                                // Display grouped options
                                foreach($cities_by_region as $region => $cities):
                                ?>
                                    <optgroup label="<?php echo $region; ?>">
                                    <?php foreach($cities as $city): 
                                        $selected = (isset($_GET['origin']) && $_GET['origin'] == $city['origin_code']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $city['origin_code']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($city['origin']); ?> (<?php echo $city['origin_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="swap-btn-container">
                            <button type="button" class="swap-btn" onclick="swapLocations()">
                                <i class="fas fa-exchange-alt"></i>
                            </button>
                        </div>

                        <div class="form-group">
                            <label for="destination"><i class="fas fa-map-marker-alt"></i> To</label>
                            <select id="destination" name="destination" required class="searchable-select">
                                <option value="">Select Destination City</option>
                                <?php 
                                $destinations->data_seek(0);
                                $dest_by_region = [];
                                while($row = $destinations->fetch_assoc()) {
                                    $region = getRegion($row['destination']);
                                    if (!isset($dest_by_region[$region])) {
                                        $dest_by_region[$region] = [];
                                    }
                                    $dest_by_region[$region][] = $row;
                                }
                                
                                foreach($dest_by_region as $region => $cities):
                                ?>
                                    <optgroup label="<?php echo $region; ?>">
                                    <?php foreach($cities as $city): 
                                        $selected = (isset($_GET['destination']) && $_GET['destination'] == $city['destination_code']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $city['destination_code']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($city['destination']); ?> (<?php echo $city['destination_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="departure_date"><i class="fas fa-calendar"></i> Departure Date</label>
                            <input type="date" id="departure_date" name="departure_date" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   value="<?php echo isset($_GET['departure_date']) ? $_GET['departure_date'] : ''; ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="seat_class"><i class="fas fa-chair"></i> Class</label>
                            <select id="seat_class" name="seat_class" required>
                                <option value="economy" <?php echo (isset($_GET['seat_class']) && $_GET['seat_class'] == 'economy') ? 'selected' : ''; ?>>Economy</option>
                                <option value="business" <?php echo (isset($_GET['seat_class']) && $_GET['seat_class'] == 'business') ? 'selected' : ''; ?>>Business</option>
                                <option value="first_class" <?php echo (isset($_GET['seat_class']) && $_GET['seat_class'] == 'first_class') ? 'selected' : ''; ?>>First Class</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn-search">
                                <i class="fas fa-search"></i> Search Flights
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- Search Results -->
        <?php if ($search_performed): ?>
        <section class="results-section">
            <div class="results-header">
                <h3>
                    <?php if ($flights && $flights->num_rows > 0): ?>
                        Found <?php echo $flights->num_rows; ?> Flight<?php echo $flights->num_rows > 1 ? 's' : ''; ?>
                    <?php else: ?>
                        No Flights Found
                    <?php endif; ?>
                </h3>
                <?php if ($flights && $flights->num_rows > 0): ?>
                <div class="sort-options">
                    <label>Sort by:</label>
                    <select onchange="sortFlights(this.value)">
                        <option value="time">Departure Time</option>
                        <option value="price_low">Price: Low to High</option>
                        <option value="price_high">Price: High to Low</option>
                        <option value="duration">Duration</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="flights-container">
                <?php if ($flights && $flights->num_rows > 0): ?>
                    <?php while($flight = $flights->fetch_assoc()): ?>
                    <div class="flight-card" data-price="<?php echo $flight['selected_price']; ?>" data-duration="<?php echo $flight['duration']; ?>">
                        <div class="flight-card-header">
                            <div class="airline-info">
                                <div class="airline-logo">
                                    <?php if ($flight['logo_url']): ?>
                                        <img src="<?php echo htmlspecialchars($flight['logo_url']); ?>" alt="<?php echo htmlspecialchars($flight['airline_name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-plane"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4><?php echo htmlspecialchars($flight['airline_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($flight['airline_code']); ?> <?php echo htmlspecialchars($flight['flight_number']); ?></p>
                                </div>
                            </div>
                            <div class="flight-status">
                                <span class="status-badge <?php echo $flight['status']; ?>">
                                    <?php echo ucfirst($flight['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="flight-card-body">
                            <div class="flight-route">
                                <div class="departure">
                                    <div class="time"><?php echo date('H:i', strtotime($flight['departure_time'])); ?></div>
                                    <div class="airport"><?php echo htmlspecialchars($flight['origin_code']); ?></div>
                                    <div class="city"><?php echo htmlspecialchars($flight['origin']); ?></div>
                                </div>

                                <div class="flight-duration">
                                    <div class="duration-line">
                                        <div class="plane-icon">
                                            <i class="fas fa-plane"></i>
                                        </div>
                                    </div>
                                    <div class="duration-text">
                                        <?php 
                                        $hours = floor($flight['duration'] / 60);
                                        $minutes = $flight['duration'] % 60;
                                        echo $hours . 'h ' . $minutes . 'm';
                                        ?>
                                    </div>
                                    <div class="flight-type">Direct</div>
                                </div>

                                <div class="arrival">
                                    <div class="time"><?php echo date('H:i', strtotime($flight['arrival_time'])); ?></div>
                                    <div class="airport"><?php echo htmlspecialchars($flight['destination_code']); ?></div>
                                    <div class="city"><?php echo htmlspecialchars($flight['destination']); ?></div>
                                </div>
                            </div>

                            <div class="flight-details">
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('M d, Y', strtotime($flight['departure_time'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-chair"></i>
                                    <span><?php echo $flight['available_seats']; ?> seats left</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-suitcase"></i>
                                    <span>Baggage included</span>
                                </div>
                            </div>
                        </div>

                        <div class="flight-card-footer">
                            <div class="price-info">
                                <span class="price-label">Price per person</span>
                                <span class="price">$<?php echo number_format($flight['selected_price'], 2); ?></span>
                            </div>
                            <button class="btn-book-flight" onclick="bookFlight(<?php echo $flight['id']; ?>, '<?php echo isset($_GET['seat_class']) ? $_GET['seat_class'] : 'economy'; ?>', <?php echo $flight['selected_price']; ?>)">
                                <i class="fas fa-ticket-alt"></i> Book Now
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-flights">
                        <i class="fas fa-plane-slash"></i>
                        <h3>No Flights Available</h3>
                        <p>We couldn't find any flights matching your search criteria.</p>
                        <p>Try adjusting your search parameters or selecting different dates.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        function swapLocations() {
            const origin = document.getElementById('origin');
            const destination = document.getElementById('destination');
            const temp = origin.value;
            origin.value = destination.value;
            destination.value = temp;
        }

        function bookFlight(flightId, seatClass, price) {
            window.location.href = `book_flight.php?flight_id=${flightId}&seat_class=${seatClass}&price=${price}`;
        }

        function sortFlights(sortBy) {
            const container = document.querySelector('.flights-container');
            const flights = Array.from(container.querySelectorAll('.flight-card'));
            
            flights.sort((a, b) => {
                switch(sortBy) {
                    case 'price_low':
                        return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                    case 'price_high':
                        return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                    case 'duration':
                        return parseInt(a.dataset.duration) - parseInt(b.dataset.duration);
                    default:
                        return 0;
                }
            });
            
            flights.forEach(flight => container.appendChild(flight));
        }

        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('departure_date');
            if (dateInput && !dateInput.value) {
                dateInput.valueAsDate = new Date();
            }

            // Add search functionality to select dropdowns
            const selects = document.querySelectorAll('.searchable-select');
            selects.forEach(select => {
                // Create wrapper
                const wrapper = document.createElement('div');
                wrapper.className = 'select-search-wrapper';
                select.parentNode.insertBefore(wrapper, select);
                wrapper.appendChild(select);

                // Create search input
                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.className = 'select-search-input';
                searchInput.placeholder = 'Type to search...';
                searchInput.style.display = 'none';
                wrapper.insertBefore(searchInput, select);

                // Show search on focus
                select.addEventListener('focus', function() {
                    searchInput.style.display = 'block';
                    searchInput.focus();
                });

                // Filter options
                searchInput.addEventListener('input', function() {
                    const filter = this.value.toLowerCase();
                    const options = select.querySelectorAll('option');
                    
                    options.forEach(option => {
                        const text = option.textContent.toLowerCase();
                        if (text.includes(filter) || option.value === '') {
                            option.style.display = 'block';
                        } else {
                            option.style.display = 'none';
                        }
                    });
                });

                // Hide search when select loses focus
                searchInput.addEventListener('blur', function() {
                    setTimeout(() => {
                        searchInput.style.display = 'none';
                        searchInput.value = '';
                        const options = select.querySelectorAll('option');
                        options.forEach(option => option.style.display = 'block');
                    }, 200);
                });
            });

            // Highlight popular routes
            highlightPopularRoutes();
        });

        function highlightPopularRoutes() {
            const popularRoutes = [
                { from: 'TUN', to: 'CDG' },
                { from: 'TUN', to: 'DXB' },
                { from: 'CDG', to: 'JFK' },
                { from: 'LHR', to: 'DXB' }
            ];

            // Add popular route suggestions
            const originSelect = document.getElementById('origin');
            const destSelect = document.getElementById('destination');

            originSelect.addEventListener('change', function() {
                const selectedOrigin = this.value;
                const popularDest = popularRoutes.find(r => r.from === selectedOrigin);
                if (popularDest) {
                    // Highlight popular destination
                    const destOption = destSelect.querySelector(`option[value="${popularDest.to}"]`);
                    if (destOption) {
                        destOption.style.fontWeight = 'bold';
                        destOption.style.color = '#0066cc';
                    }
                }
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>