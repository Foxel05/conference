import mitt from 'mitt'

/**
 * Global event bus for component communication
 * Replaces Vue 2's $root.$on/$emit pattern
 * 
 * Usage:
 *   import eventBus from '@/utils/eventBus'
 *   
 *   // Listen for event
 *   eventBus.on('my-event', (data) => { ... })
 *   
 *   // Emit event
 *   eventBus.emit('my-event', data)
 *   
 *   // Listen once
 *   eventBus.once('my-event', (data) => { ... })
 *   
 *   // Unregister listener
 *   eventBus.off('my-event', handler)
 *   
 *   // Clear all listeners
 *   eventBus.all.clear()
 */
const eventBus = mitt()

export default eventBus
