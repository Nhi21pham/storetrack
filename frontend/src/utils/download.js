// Triggers a browser "save as" for a Blob fetched over the API (axios doesn't
// honour Content-Disposition for XHR responses, so we set the name client-side).
export const triggerBlobDownload = (blob, filename) => {
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
