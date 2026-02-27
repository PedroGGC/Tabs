document.addEventListener("DOMContentLoaded", () => {
  const bellBtn = document.getElementById("bell-toggle");
  const badge = document.getElementById("bell-badge");
  const dropdown = document.getElementById("notifications-dropdown");

  // Avatar dropdown
  const avatarBtn = document.getElementById("avatar-toggle");
  const profileDropdown = document.getElementById("profile-dropdown");


    if (!bellBtn || !dropdown) {
        console.warn("Notifications elements missing, aborting");
        return;
    }


    let hasFetched = false;
    let isOpen = false;

  // Polling function
  async function fetchNotifications() {
    try {
      const response = await fetch("api/notifications.php?action=fetch");
      if (response.ok) {
        const data = await response.json();

        // Update badge
        if (data.unread_count > 0 && badge) {
          badge.style.display = "flex";
          badge.textContent =
            data.unread_count > 99 ? "99+" : data.unread_count;
        } else if (badge) {
          badge.style.display = "none";
        }

        // Render list
        if (data.notifications.length === 0) {
          dropdown.innerHTML =
            '<div class="dropdown-empty">Não há mensagens no momento.</div>';
        } else {
          dropdown.innerHTML = "";
          data.notifications.forEach((n) => {
            const item = document.createElement("a");
            item.href = "post.php?id=" + n.post_id;
            item.className = "dropdown-item" + (!n.is_read ? " unread" : "");
            item.innerHTML = n.message;
            dropdown.appendChild(item);
          });
        }
      }
    } catch (e) {
      console.error("Failed to fetch notifications", e);
    }
  }

  // Mark as read
  async function markAsRead() {
    try {
      const formData = new FormData();
      const tokenMeta = document.querySelector('meta[name="_csrf"]');
      if (tokenMeta) {
        formData.append("_csrf", tokenMeta.getAttribute("content"));
      }
      await fetch("api/notifications.php?action=read_all", {
        method: "POST",
        body: formData,
      });
      if (badge) badge.style.display = "none";
    } catch (e) {
      console.error(e);
    }
  }

  // Notification dropdown toggler
  bellBtn.addEventListener("click", (e) => {
    e.stopPropagation();

    // Add physical feedback animation to bell
    bellBtn.classList.add("bell-shake");
    setTimeout(() => {
	    bellBtn.classList.remove("bell-shake");
    }, 500);

    isOpen = !isOpen;
    if (isOpen) {
        dropdown.classList.add("is-open");
    } else {
        dropdown.classList.remove("is-open");
    }

    if (isOpen) {
      if (profileDropdown && profileDropdown.classList.contains("is-open")) {
        profileDropdown.classList.remove("is-open");
      }

      if (!hasFetched) {
        dropdown.innerHTML = '<div class="dropdown-empty">Carregando...</div>';
        fetchNotifications().then(() => {
          markAsRead();
        });
        hasFetched = true;
      } else {
        markAsRead();
      }
    }
  });

  // Profile dropdown toggler
  if (avatarBtn && profileDropdown) {
    avatarBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      const pIsOpen = profileDropdown.classList.contains("is-open");
      
      if (pIsOpen) {
          profileDropdown.classList.remove("is-open");
      } else {
          profileDropdown.classList.add("is-open");
      }

      if (!pIsOpen && isOpen) {
        dropdown.classList.remove("is-open");
        isOpen = false;
      }
    });
  }

  // Close dropdowns when clicking outside
  document.addEventListener("click", (e) => {
    if (isOpen && !dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
      dropdown.classList.remove("is-open");
      isOpen = false;
    }

    if (
      profileDropdown &&
      profileDropdown.classList.contains("is-open") &&
      !profileDropdown.contains(e.target) &&
      !avatarBtn.contains(e.target)
    ) {
      profileDropdown.classList.remove("is-open");
    }
  });

  // Initial pull
  fetchNotifications();

  // Poll every 30 seconds
  setInterval(fetchNotifications, 30000);
});
