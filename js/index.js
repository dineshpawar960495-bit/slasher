document.addEventListener("DOMContentLoaded", () => {
    const progressFill = document.getElementById("progress-fill");
    const statusText = document.getElementById("status-text");
    
    // Realistic loading messages
    const loadingSteps = [
        { progress: 15, text: "Connecting to secure server..." },
        { progress: 35, text: "Syncing your subscriptions..." },
        { progress: 60, text: "Identifying potential savings..." },
        { progress: 85, text: "Optimizing dashboard UI..." },
        { progress: 100, text: "Calculation complete!" }
    ];

    let currentStep = 0;

    const runLoader = () => {
        if (currentStep < loadingSteps.length) {
            const step = loadingSteps[currentStep];
            
            progressFill.style.width = `${step.progress}%`;
            statusText.innerText = step.text;

            currentStep++;
            
            // Randomize timing a bit so it doesn't look like a perfect robot
            const delay = Math.floor(Math.random() * 800) + 400; 
            setTimeout(runLoader, delay);
        } else {
            // After loading is done, fade out or redirect
            setTimeout(() => {
                document.body.style.opacity = "0";
                document.body.style.transition = "opacity 0.8s ease";
                
                // Redirecting to your main dashboard page
                // window.location.href = "dashboard.html"; 
                console.log("Loading Complete. Redirecting...");
            }, 600);
        }
    };

    // Start the process
    runLoader();
});