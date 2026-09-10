class Toast{constructor(){this.icons={success:'<span class="glyphicon glyphicon-ok-sign text-success"></span>',error:'<span class="glyphicon glyphicon-remove-sign text-danger"></span>',warning:'<span class="glyphicon glyphicon-warning-sign text-warning"></span>',info:'<span class="glyphicon glyphicon-info-sign text-info"></span>'},this.defaultTitles={success:"Success!",error:"Error!",warning:"Warning!",info:"Info"},this.ensureContainer(),this.setupScrollListener()}debugLog(t,e=null){}setupScrollListener(){let t,e=()=>{let t=document.getElementById("toastContainer");if(!t)return;let e=window.scrollY||window.pageYOffset||document.documentElement.scrollTop||0;t.children.length>0&&(t.style.transition="top 0.2s ease-out",t.style.position="absolute",t.style.top=e+20+"px",t.style.right="20px",t.style.zIndex="99999")},s=()=>{clearTimeout(t),t=setTimeout(()=>{e()},150)};window.addEventListener("scroll",s,{passive:!0}),window.addEventListener("resize",()=>{setTimeout(e,100)},{passive:!0}),setTimeout(e,100)}ensureContainer(){let t=document.getElementById("toastContainer");if(t){this.updateContainerPosition(t);return}(t=document.createElement("div")).id="toastContainer",t.className="toast-container",this.updateContainerPosition(t),document.body.appendChild(t),this.debugLog("Container created")}updateContainerPosition(t){let e=window.scrollY||window.pageYOffset||document.documentElement.scrollTop||0;t.style.position="absolute",t.style.top=e+20+"px",t.style.right="20px",t.style.zIndex="99999",t.style.pointerEvents="none",t.style.maxHeight="calc(100vh - 40px)",t.style.overflow="hidden"}show(t="info",e="",s="",n=4e3){this.ensureContainer();let o="toast-"+Date.now()+"-"+Math.random().toString(36).substring(2,8),i=e||this.defaultTitles[t]||"Notification",r=this.icons[t]||this.icons.info,a=`
            <div class="toast ${t}" id="${o}" style="display: none;">
                <div class="toast-header">
                    <div class="toast-title">
                        ${r}
                        ${i}
                    </div>
                    <button class="toast-close" onclick="toast.close('${o}')">&times;</button>
                </div>
                <div class="toast-body">${s}</div>
                <div class="toast-progress">
                    <div class="toast-progress-bar" id="${o}-progress"></div>
                </div>
            </div>
        `,l=document.getElementById("toastContainer");l.insertAdjacentHTML("beforeend",a);let d=document.getElementById(o),c=document.getElementById(`${o}-progress`);return this.applyToastStyles(d,t),setTimeout(()=>{d.style.display="block",d.style.opacity="0",d.style.transform="translateX(100%)",requestAnimationFrame(()=>{d.style.transition="opacity 0.3s, transform 0.3s",d.style.opacity="1",d.style.transform="translateX(0)"})},50),c&&setTimeout(()=>{c.style.transition=`width ${n}ms linear`,c.style.width="100%"},100),setTimeout(()=>this.close(o),n),setTimeout(()=>this.updateContainerPosition(l),200),o}applyToastStyles(t,e){let s={success:"#5cb85c",error:"#d9534f",warning:"#f0ad4e",info:"#5bc0de"};t.style.cssText+=`
            min-width: 300px;
            max-width: 400px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-left: 5px solid ${s[e]||s.info};
            border-radius: 4px;
            margin-bottom: 10px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.175);
            pointer-events: auto;
        `}close(t){let e=document.getElementById(t);e&&(e.style.animation="slideOutRight 0.3s ease-in",setTimeout(()=>{e&&e.parentNode&&e.parentNode.removeChild(e)},300))}checkPosition(){}addVisualIndicator(){}success(t,e="",s=4e3){return this.show("success",e,t,s)}error(t,e="",s=5e3){return this.show("error",e,t,s)}warning(t,e="",s=4500){return this.show("warning",e,t,s)}info(t,e="",s=4e3){return this.show("info",e,t,s)}clearAll(){let t=document.getElementById("toastContainer");t&&(t.innerHTML="")}}const toast=new Toast;"undefined"!=typeof module&&module.exports&&(module.exports=Toast);
