
class Component {

    constructor(name, enabled) {
		this.name = name;
		this.enabled = enabled;
    }
    
    toArray() {
        
        return {
            'enabled' : this.enabled
        };
    }
}

module.exports = Component;