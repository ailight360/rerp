/**
 * Kanban Board - Drag and Drop Functionality
 * Vanilla JavaScript, no frameworks
 */

document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');
    
    let draggedCard = null;
    
    // Add drag event listeners to cards
    cards.forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });
    
    // Add drop event listeners to columns
    columns.forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('dragenter', handleDragEnter);
        column.addEventListener('dragleave', handleDragLeave);
        column.addEventListener('drop', handleDrop);
    });
    
    function handleDragStart(e) {
        draggedCard = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.id);
    }
    
    function handleDragEnd(e) {
        this.classList.remove('dragging');
        draggedCard = null;
        
        // Remove highlighting from all columns
        columns.forEach(col => col.classList.remove('drag-over'));
    }
    
    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }
    
    function handleDragEnter(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    }
    
    function handleDragLeave(e) {
        this.classList.remove('drag-over');
    }
    
    function handleDrop(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        
        const newStatus = this.dataset.status;
        const cardId = e.dataTransfer.getData('text/plain');
        
        if (cardId && newStatus) {
            updateCardStatus(cardId, newStatus);
        }
    }
    
    function updateCardStatus(cardId, newStatus) {
        const formData = new FormData();
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
        formData.append('id', cardId);
        formData.append('status', newStatus);
        
        fetch('/work-orders?action=update-status', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Work order status updated', 'success');
                // Optionally reload the page after a delay
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Failed to update status', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Failed to update status', 'error');
        });
    }
});
