export default function authHeader() {
  if (typeof window === "undefined") {
    return {}
  }

  const storedAuthUser = sessionStorage.getItem("authUser")
  const obj = storedAuthUser ? JSON.parse(storedAuthUser) : null

  if (obj && obj.accessToken) {
    return { Authorization: obj.accessToken }
  } else {
    return {}
  }
}
