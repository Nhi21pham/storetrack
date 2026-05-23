import { ref, onBeforeUnmount } from 'vue'

const MIN_COLUMN_WIDTH = 50

export const useColumnResize = (initialWidths) => {
  const colWidths  = ref([...initialWidths])
  const isResizing = ref(false)
  let drag = null

  const onMouseMove = (e) => {
    if (!drag) return
    const delta = e.clientX - drag.startX
    colWidths.value[drag.index] = Math.max(MIN_COLUMN_WIDTH, drag.startWidth + delta)
  }

  const onMouseUp = () => {
    drag = null
    isResizing.value = false
    window.removeEventListener('mousemove', onMouseMove)
    window.removeEventListener('mouseup', onMouseUp)
  }

  const startResize = (event, index) => {
    drag = {
      index,
      startX: event.clientX,
      startWidth: colWidths.value[index],
    }
    isResizing.value = true
    window.addEventListener('mousemove', onMouseMove)
    window.addEventListener('mouseup', onMouseUp)
  }

  onBeforeUnmount(() => {
    isResizing.value = false
    drag = null
    window.removeEventListener('mousemove', onMouseMove)
    window.removeEventListener('mouseup', onMouseUp)
  })

  return { colWidths, isResizing, startResize }
}
