/*
 * http://www.ietf.org/rfc/rfc3174
 */
function Sha1() {
	this._h0 = 0x67452301;
	this._h1 = 0xefcdab89;
	this._h2 = 0x98badcfe;
	this._h3 = 0x10325476;
	this._h4 = 0xc3d2e1f0;

	this._length = 0;
	this._finalized = false;
	this._w = new Array(80);
  
	/*
	 * Update the SHA1 hash
	 * Note: each chunk size MUST be a multiple of 64 bytes, excepted for the last one. 
	 */

	this.hash = function(chunk) {  	
		this._length += chunk.length;
  	
		var n = chunk.length >>> 6;

		var i = 0;
		while(i<n) {
			this._hash(chunk.substr(i<<6,64));
			i++;
		}
  	
		// last block
		if(chunk.length%64>0) {
			this._hash(chunk.substr(i<<6) + String.fromCharCode(0x80));  		
		}
	};
    
  
  /*
   * Update the SHA1 hash for the given 64 bytes block.
   * This function MUST no be called outside the object (private).
   */
  this._hash = function(block) {
    var a = this._h0;
    var b = this._h1;
    var c = this._h2;
    var d = this._h3;
    var e = this._h4;  	

    var t=0;
    var temp;
  	
    while(t<16) {
      this._w[t] = (block.charCodeAt(t<<2)<<24) | (block.charCodeAt((t<<2)+1)<<16) | (block.charCodeAt((t<<2)+2)<<8) | (block.charCodeAt((t<<2)+3));
      t++;
    } 

    // last block must contain the length of the file (in bits)
    if(block.length <= 54) {
    	this._w[14] = this._length>>>29; // 32-3
    	this._w[15] = (this._length<<3) & 0xffffffff;
    }    
       
    while(t<80) {
    	temp = this._w[t-3] ^ this._w[t-8] ^ this._w[t-14] ^ this._w[t-16];
    	this._w[t] = (temp<<1)|(temp>>>31);
    	t++;
    }       
    
    t = 0;
    while(t<20) {
    	temp = ((a<<5)|(a>>>27)) + ((b&c)|(~b&d)) + e + this._w[t] + 0x5a827999;
      e = d;
      d = c;
      c = (b<<30)|(b>>>2);
      b = a;
      a = temp;
    	t++;
    }
    
    while(t<40) {
      temp = ((a<<5)|(a>>>27)) + (b^c^d) + e + this._w[t] + 0x6ed9eba1;    	
      e = d;
      d = c;
      c = (b<<30)|(b>>>2);
      b = a;
      a = temp;
    	t++;
    }
    
    while(t<60) {
      temp = ((a<<5)|(a>>>27)) + ((b&c)|(b&d)|(c&d)) + e + this._w[t] + 0x8f1bbcdc;    	
      e = d;
      d = c;
      c = (b<<30)|(b>>>2);
      b = a;
      a = temp;
    	t++;
    }
    
    while(t<80) {    	
      temp = ((a<<5)|(a>>>27)) + (b^c^d) + e + this._w[t] + 0xca62c1d6;    	
      e = d;
      d = c;
      c = (b<<30)|(b>>>2);
      b = a;
      a = temp;
    	t++;
    }
    
    this._h0 = (this._h0 + a) & 0xffffffff;  
    this._h1 = (this._h1 + b) & 0xffffffff; 
    this._h2 = (this._h2 + c) & 0xffffffff; 
    this._h3 = (this._h3 + d) & 0xffffffff; 
    this._h4 = (this._h4 + e) & 0xffffffff;
  };

  /*
   * Returns the sha1
   */
  this.result = function() {
  	
  	if(!this._finalized) {
  		var remainder = this._length%64; 
  		if(remainder>54) {
  			this._hash('');
  		} else if(remainder == 0) {
  			this._hash(String.fromCharCode(0x80));
  		}
  		this._finalized = true;
  	}
  	
    return Sha1.toHexStr(this._h0) + Sha1.toHexStr(this._h1) + Sha1.toHexStr(this._h2) + Sha1.toHexStr(this._h3) + Sha1.toHexStr(this._h4);  	  	
  };
  
  return true;
}

Sha1.toHexStr = function(n) {
  var result='';
  for (var i=7; i>=0; i--) result += ((n>>>(i<<2)) & 0xf).toString(16); 
  return result;
};
