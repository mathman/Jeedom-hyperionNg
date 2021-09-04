const Component = require('./Component');

class Priority {

    constructor(active, componentId, owner, priority, visible, origin, value, duration_ms) {
        this.active = active;
		this.componentId = componentId;
		this.priority = priority;
		this.visible = visible;
		this.owner = owner;
		this.origin = origin;
		this.value = value;
		this.duration_ms = duration_ms;
    }
	
	isActive() {
		
		return this.active;
	}
	
	getPriority() {
		
		return this.priority;
	}
    
    toArray() {
        
        return {
            'active' : this.active,
            'owner' : this.owner,
            'priority' : this.priority,
            'visible' : this.visible,
			'origin' : this.origin,
			'componentId' : this.componentId,
			'value' : this.value,
			'duration_ms' : this.duration_ms
        };
    }
}

module.exports = Priority;