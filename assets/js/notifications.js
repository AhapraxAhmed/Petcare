// Notification system JavaScript
class NotificationManager {
  constructor() {
    this.notifications = []
    this.init()
  }

  init() {
    this.createNotificationContainer()
    this.loadNotifications()
    this.setupEventListeners()

    // Check for new notifications every 30 seconds
    setInterval(() => {
      this.loadNotifications()
    }, 30000)
  }

  createNotificationContainer() {
    if (!document.getElementById("notification-container")) {
      const container = document.createElement("div")
      container.id = "notification-container"
      container.className = "fixed top-4 right-4 z-50 space-y-2"
      document.body.appendChild(container)
    }
  }

  async loadNotifications() {
    try {
      const response = await fetch("/api/notifications.php")
      const data = await response.json()

      if (data.notifications) {
        this.updateNotificationBadge(data.notifications.filter((n) => !n.is_read).length)
        this.notifications = data.notifications
      }
    } catch (error) {
      console.error("Error loading notifications:", error)
    }
  }

  updateNotificationBadge(count) {
    const badge = document.getElementById("notification-badge")
    if (badge) {
      if (count > 0) {
        badge.textContent = count > 99 ? "99+" : count
        badge.classList.remove("hidden")
      } else {
        badge.classList.add("hidden")
      }
    }
  }

  async markAsRead(notificationId) {
    try {
      const response = await fetch("/api/notifications.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ notification_id: notificationId }),
      })

      if (response.ok) {
        this.loadNotifications()
      }
    } catch (error) {
      console.error("Error marking notification as read:", error)
    }
  }

  showToast(title, message, type = "info") {
    const toast = document.createElement("div")
    toast.className = `notification-toast transform transition-all duration-300 translate-x-full opacity-0 max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden`

    const typeColors = {
      info: "bg-blue-500",
      success: "bg-green-500",
      warning: "bg-yellow-500",
      error: "bg-red-500",
      health: "bg-purple-500",
      reminder: "bg-orange-500",
      application: "bg-indigo-500",
    }

    const typeIcons = {
      info: "fas fa-info-circle",
      success: "fas fa-check-circle",
      warning: "fas fa-exclamation-triangle",
      error: "fas fa-times-circle",
      health: "fas fa-heart",
      reminder: "fas fa-bell",
      application: "fas fa-file-alt",
    }

    toast.innerHTML = `
            <div class="flex p-4">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 ${typeColors[type]} rounded-full flex items-center justify-center">
                        <i class="${typeIcons[type]} text-white text-sm"></i>
                    </div>
                </div>
                <div class="ml-3 w-0 flex-1">
                    <p class="text-sm font-medium text-gray-900">${title}</p>
                    <p class="mt-1 text-sm text-gray-500">${message}</p>
                </div>
                <div class="ml-4 flex-shrink-0 flex">
                    <button class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none" onclick="this.closest('.notification-toast').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `

    const container = document.getElementById("notification-container")
    container.appendChild(toast)

    // Animate in
    setTimeout(() => {
      toast.classList.remove("translate-x-full", "opacity-0")
    }, 100)

    // Auto remove after 5 seconds
    setTimeout(() => {
      toast.classList.add("translate-x-full", "opacity-0")
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast)
        }
      }, 300)
    }, 5000)
  }

  setupEventListeners() {
    // Listen for notification bell click
    document.addEventListener("click", (e) => {
      if (e.target.closest("#notification-bell")) {
        this.toggleNotificationDropdown()
      }
    })
  }

  toggleNotificationDropdown() {
    const dropdown = document.getElementById("notification-dropdown")
    if (dropdown) {
      dropdown.classList.toggle("hidden")
      this.renderNotificationDropdown()
    }
  }

  renderNotificationDropdown() {
    const dropdown = document.getElementById("notification-dropdown")
    if (!dropdown) return

    const unreadNotifications = this.notifications.filter((n) => !n.is_read).slice(0, 5)

    if (unreadNotifications.length === 0) {
      dropdown.innerHTML = `
                <div class="p-4 text-center text-gray-500">
                    <i class="fas fa-bell-slash text-2xl mb-2"></i>
                    <p>No new notifications</p>
                </div>
            `
      return
    }

    dropdown.innerHTML = unreadNotifications
      .map(
        (notification) => `
            <div class="p-3 hover:bg-gray-50 border-b cursor-pointer" onclick="notificationManager.markAsRead(${notification.notification_id})">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                        <p class="text-xs text-gray-500 mt-1">${notification.message}</p>
                        <p class="text-xs text-gray-400 mt-1">${new Date(notification.created_at).toLocaleDateString()}</p>
                    </div>
                </div>
            </div>
        `,
      )
      .join("")
  }
}

// Initialize notification manager when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.notificationManager = new NotificationManager()
})

// Add notification bell to navigation (call this function in your dashboard templates)
function addNotificationBell() {
  const nav = document.querySelector("nav, .navbar, .header-nav")
  if (nav && !document.getElementById("notification-bell")) {
    const bellContainer = document.createElement("div")
    bellContainer.className = "relative"
    bellContainer.innerHTML = `
            <button id="notification-bell" class="relative p-2 text-gray-600 hover:text-gray-800 focus:outline-none">
                <i class="fas fa-bell text-xl"></i>
                <span id="notification-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
            </button>
            <div id="notification-dropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border hidden z-50 max-h-96 overflow-y-auto">
                <!-- Notifications will be populated here -->
            </div>
        `
    nav.appendChild(bellContainer)
  }
}
