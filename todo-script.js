// Local Storage Key
const STORAGE_KEY = 'todoList';
let currentFilter = 'all';
let todos = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    loadTodos();
    setupEventListeners();
    updateStats();
    renderTodos();
});

// Setup Event Listeners
function setupEventListeners() {
    const input = document.getElementById('todoInput');
    const addBtn = document.getElementById('addBtn');
    const clearBtn = document.getElementById('clearBtn');
    const clearAllBtn = document.getElementById('clearAllBtn');
    const filterBtns = document.querySelectorAll('.filter-btn');

    // Add todo
    addBtn.addEventListener('click', addTodo);
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') addTodo();
    });

    // Clear buttons
    clearBtn.addEventListener('click', clearCompleted);
    clearAllBtn.addEventListener('click', clearAll);

    // Filter buttons
    filterBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            filterBtns.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            currentFilter = e.target.dataset.filter;
            renderTodos();
        });
    });
}

// Add Todo
function addTodo() {
    const input = document.getElementById('todoInput');
    const text = input.value.trim();

    if (!text) {
        alert('Please enter a task!');
        return;
    }

    const todo = {
        id: Date.now(),
        text: text,
        completed: false,
        created: new Date().toISOString()
    };

    todos.unshift(todo);
    saveTodos();
    input.value = '';
    renderTodos();
    updateStats();
}

// Toggle todo completion
function toggleTodo(id) {
    const todo = todos.find(t => t.id === id);
    if (todo) {
        todo.completed = !todo.completed;
        saveTodos();
        renderTodos();
        updateStats();
    }
}

// Delete todo
function deleteTodo(id) {
    if (confirm('Delete this task?')) {
        todos = todos.filter(t => t.id !== id);
        saveTodos();
        renderTodos();
        updateStats();
    }
}

// Clear completed todos
function clearCompleted() {
    const completedCount = todos.filter(t => t.completed).length;
    if (completedCount === 0) {
        alert('No completed tasks to clear!');
        return;
    }

    if (confirm(`Delete ${completedCount} completed task(s)?`)) {
        todos = todos.filter(t => !t.completed);
        saveTodos();
        renderTodos();
        updateStats();
    }
}

// Clear all todos
function clearAll() {
    if (todos.length === 0) {
        alert('No tasks to clear!');
        return;
    }

    if (confirm('⚠️ Delete all tasks? This cannot be undone!')) {
        todos = [];
        saveTodos();
        renderTodos();
        updateStats();
    }
}

// Filter and render todos
function renderTodos() {
    const container = document.getElementById('todosContainer');
    let filtered = todos;

    if (currentFilter === 'active') {
        filtered = todos.filter(t => !t.completed);
    } else if (currentFilter === 'completed') {
        filtered = todos.filter(t => t.completed);
    }

    if (filtered.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <p class="empty-icon">📝</p>
                <p class="empty-text">${currentFilter === 'all' ? 'No tasks yet. Add one to get started!' : `No ${currentFilter} tasks.`}</p>
            </div>
        `;
        return;
    }

    container.innerHTML = filtered.map(todo => createTodoElement(todo)).join('');
}

// Create todo element HTML
function createTodoElement(todo) {
    const date = new Date(todo.created);
    const timeStr = formatDate(date);
    const completedClass = todo.completed ? 'completed' : '';

    return `
        <div class="todo-item ${completedClass}">
            <input 
                type="checkbox" 
                class="todo-checkbox" 
                ${todo.completed ? 'checked' : ''}
                onchange="toggleTodo(${todo.id})"
            >
            <div class="todo-content">
                <div class="todo-text">${escapeHtml(todo.text)}</div>
                <div class="todo-time">Created ${timeStr}</div>
            </div>
            <div class="todo-actions">
                <button class="todo-btn delete-btn" onclick="deleteTodo(${todo.id})" title="Delete">🗑️</button>
            </div>
        </div>
    `;
}

// Update statistics
function updateStats() {
    const total = todos.length;
    const active = todos.filter(t => !t.completed).length;
    const completed = todos.filter(t => t.completed).length;

    document.getElementById('totalCount').textContent = total;
    document.getElementById('activeCount').textContent = active;
    document.getElementById('completedCount').textContent = completed;
}

// Save todos to local storage
function saveTodos() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(todos));
}

// Load todos from local storage
function loadTodos() {
    const data = localStorage.getItem(STORAGE_KEY);
    todos = data ? JSON.parse(data) : [];
}

// Format date
function formatDate(date) {
    const now = new Date();
    const diff = now - date;
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (seconds < 60) return 'just now';
    if (minutes < 60) return `${minutes} minute(s) ago`;
    if (hours < 24) return `${hours} hour(s) ago`;
    if (days < 7) return `${days} day(s) ago`;

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}