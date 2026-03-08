// ============================================
// CUSTOMER NAME AUTOCOMPLETE FUNCTIONALITY
// Add this JavaScript code to your order.php file
// ============================================

// Autocomplete for Customer Name
(function() {
  const customerNameInput = document.getElementById('customerName');
  const customerIdInput = document.getElementById('customerId');
  const customerPhoneInput = document.getElementById('customerPhone');
  const customerAddressInput = document.getElementById('customerAddress');
  const manufacturerInput = document.getElementById('manufacturer');
  
  // Create autocomplete wrapper and suggestions container
  const wrapper = document.createElement('div');
  wrapper.className = 'autocomplete-wrapper';
  customerNameInput.parentNode.insertBefore(wrapper, customerNameInput);
  wrapper.appendChild(customerNameInput);
  
  const suggestionsDiv = document.createElement('div');
  suggestionsDiv.className = 'autocomplete-suggestions';
  suggestionsDiv.id = 'customerSuggestions';
  wrapper.appendChild(suggestionsDiv);
  
  let searchTimeout;
  let selectedIndex = -1;
  let suggestions = [];
  
  // Search customers by name
  function searchCustomers(query) {
    if (query.length < 1) {
      hideSuggestions();
      return;
    }
    
    // Show loading
    suggestionsDiv.innerHTML = '<div class="autocomplete-loading">🔍 Searching...</div>';
    suggestionsDiv.classList.add('active');
    
    fetch('search_customers.php?query=' + encodeURIComponent(query))
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data.length > 0) {
          suggestions = data.data;
          displaySuggestions(suggestions);
        } else {
          suggestionsDiv.innerHTML = '<div class="autocomplete-no-results">No customers found</div>';
        }
      })
      .catch(error => {
        console.error('Search error:', error);
        hideSuggestions();
      });
  }
  
  // Display suggestions
  function displaySuggestions(customers) {
    suggestionsDiv.innerHTML = '';
    selectedIndex = -1;
    
    customers.forEach((customer, index) => {
      const item = document.createElement('div');
      item.className = 'autocomplete-item';
      item.dataset.index = index;
      
      item.innerHTML = `
        <div class="customer-name">
          <span class="customer-id">ID: ${escapeHtml(customer.id)}</span>
          ${escapeHtml(customer.name)}
        </div>
        <div class="customer-details">
          📱 ${escapeHtml(customer.phone)} 
          ${customer.address ? '| 📍 ' + escapeHtml(customer.address) : ''}
        </div>
      `;
      
      item.addEventListener('click', () => selectCustomer(customer));
      suggestionsDiv.appendChild(item);
    });
    
    suggestionsDiv.classList.add('active');
  }
  
  // Select customer from suggestions
  function selectCustomer(customer) {
    customerIdInput.value = customer.id;
    customerNameInput.value = customer.name;
    customerPhoneInput.value = customer.phone;
    customerAddressInput.value = customer.address || '';
    if (customer.manufacturer) {
      manufacturerInput.value = customer.manufacturer;
    }
    hideSuggestions();
    
    // Visual feedback
    customerNameInput.style.background = '#d4edda';
    setTimeout(() => {
      customerNameInput.style.background = '';
    }, 500);
  }
  
  // Hide suggestions
  function hideSuggestions() {
    suggestionsDiv.classList.remove('active');
    selectedIndex = -1;
  }
  
  // Handle keyboard navigation
  function handleKeyNavigation(e) {
    const items = suggestionsDiv.querySelectorAll('.autocomplete-item');
    
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
      updateSelection(items);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedIndex = Math.max(selectedIndex - 1, -1);
      updateSelection(items);
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
      e.preventDefault();
      if (suggestions[selectedIndex]) {
        selectCustomer(suggestions[selectedIndex]);
      }
    } else if (e.key === 'Escape') {
      hideSuggestions();
    }
  }
  
  // Update visual selection
  function updateSelection(items) {
    items.forEach((item, index) => {
      if (index === selectedIndex) {
        item.style.background = '#e7f3ff';
      } else {
        item.style.background = '';
      }
    });
    
    if (selectedIndex >= 0 && items[selectedIndex]) {
      items[selectedIndex].scrollIntoView({ block: 'nearest' });
    }
  }
  
  // Event listeners
  customerNameInput.addEventListener('input', function(e) {
    const query = this.value.trim();
    
    clearTimeout(searchTimeout);
    
    if (query.length >= 1) {
      searchTimeout = setTimeout(() => {
        searchCustomers(query);
      }, 300); // Debounce for 300ms
    } else {
      hideSuggestions();
    }
  });
  
  customerNameInput.addEventListener('keydown', handleKeyNavigation);
  
  // Close suggestions when clicking outside
  document.addEventListener('click', function(e) {
    if (!wrapper.contains(e.target)) {
      hideSuggestions();
    }
  });
  
  // Clear customer ID when manually typing new name
  let previousValue = customerNameInput.value;
  customerNameInput.addEventListener('input', function() {
    if (this.value !== previousValue) {
      // Only clear ID if user is typing something different
      if (!suggestionsDiv.classList.contains('active')) {
        customerIdInput.value = '';
      }
    }
    previousValue = this.value;
  });
  
})();

// Helper function for escaping HTML (if not already defined)
if (typeof escapeHtml === 'undefined') {
  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
}