class Effect {

    constructor(file, name, script) {
        this.file = file;
		this.name = name;
		this.script = script;
    }
	
	getName() {
		
		return this.name;
	}
    
    toArray() {
        
        return {
            'file' : this.file,
            'script' : this.script
        };
    }
}

module.exports = Effect;