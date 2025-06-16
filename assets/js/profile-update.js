// Real-time profile picture update across all pages
function updateProfilePictureGlobally(newImagePath) {
  // Update all profile images on the current page
  const profileImages = document.querySelectorAll('img[alt="Profile"], .profile-image')
  const profileInitials = document.querySelectorAll(".profile-initials")

  if (newImagePath) {
    // Update existing images
    profileImages.forEach((img) => {
      img.src = newImagePath
      img.style.display = "block"
    })

    // Hide initial placeholders
    profileInitials.forEach((initial) => {
      initial.style.display = "none"
    })

    // Create new image elements where needed
    const profileContainers = document.querySelectorAll(".profile-container")
    profileContainers.forEach((container) => {
      if (!container.querySelector("img")) {
        const img = document.createElement("img")
        img.src = newImagePath
        img.alt = "Profile"
        img.className = "w-full h-full rounded-full object-cover"
        container.innerHTML = ""
        container.appendChild(img)
      }
    })
  }

  // Store in localStorage for persistence across page loads
  localStorage.setItem("user_profile_image", newImagePath || "")
  localStorage.setItem("profile_updated_at", Date.now())
}

// Check for profile updates on page load
document.addEventListener("DOMContentLoaded", () => {
  const storedImage = localStorage.getItem("user_profile_image")
  const updatedAt = localStorage.getItem("profile_updated_at")

  // If image was updated in the last 5 minutes, apply it
  if (storedImage && updatedAt && Date.now() - updatedAt < 300000) {
    updateProfilePictureGlobally(storedImage)
  }
})

// Listen for profile update events
window.addEventListener("storage", (e) => {
  if (e.key === "user_profile_image") {
    updateProfilePictureGlobally(e.newValue)
  }
})

// Function to trigger profile update notification
function notifyProfileUpdate(imagePath) {
  updateProfilePictureGlobally(imagePath)

  // Dispatch custom event for other scripts
  window.dispatchEvent(
    new CustomEvent("profileUpdated", {
      detail: { imagePath: imagePath },
    }),
  )
}
