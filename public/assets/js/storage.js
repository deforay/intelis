

    class StorageHelper {
        static isSupported() {
            try {
                const storage = window['localStorage'];
                const x = '__storage_test__';
                storage.setItem(x, x);
                storage.removeItem(x);
                return true;
            } catch (e) {
                return e instanceof DOMException && (
                        // everything except Firefox
                        e.name === 'QuotaExceededError' ||
                        // Firefox
                        e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
                    // acknowledge QuotaExceededError only if there's something already stored
                    storage.length !== 0;
            }
        }

        static storeInLocalStorage(key, value) {
            if (!StorageHelper.isSupported()) {
                console.error('localStorage is not supported in this browser');
                return;
            }

            try {
                if (typeof value !== 'string') {
                    value = JSON.stringify(value);
                }
                localStorage.setItem(key, value);
            } catch (error) {
                console.error(`Error storing item in localStorage: ${error}`);
            }
        }

        static getFromLocalStorage(key) {
            if (!StorageHelper.isSupported()) {
                console.error('localStorage is not supported in this browser');
                return;
            }

            const value = localStorage.getItem(key);
            try {
                return JSON.parse(value);
            } catch (error) {
                return value;
            }
        }

        static removeItemFromLocalStorage(key) {
            if (!StorageHelper.isSupported()) {
                console.error('localStorage is not supported in this browser');
                return;
            }

            try {
                localStorage.removeItem(key);
            } catch (error) {
                console.error(`Error removing item from localStorage: ${error}`);
            }
        }

        static clearLocalStorage() {
            if (!StorageHelper.isSupported()) {
                console.error('localStorage is not supported in this browser');
                return;
            }

            try {
                localStorage.clear();
            } catch (error) {
                console.error(`Error clearing localStorage: ${error}`);
            }
        }

        static storeInSessionStorage(key, value) {
            if (!StorageHelper.isSupported()) {
                console.error('sessionStorage is not supported in this browser');
                return;
            }

            try {
                if (typeof value !== 'string') {
                    value = JSON.stringify(value);
                }
                sessionStorage.setItem(key, value);
            } catch (error) {
                console.error(`Error storing item in sessionStorage: ${error}`);
            }
        }

        static getFromSessionStorage(key) {
            if (!StorageHelper.isSupported()) {
                console.error('sessionStorage is not supported in this browser');
                return;
            }

            const value = sessionStorage.getItem(key);
            try {
                return JSON.parse(value);
            } catch (error) {
                return value;
            }
        }

        static removeItemFromSessionStorage(key) {
            if (!StorageHelper.isSupported()) {
                console.error('sessionStorage is not supported in this browser');
                return;
            }

            try {
                sessionStorage.removeItem(key);
            } catch (error) {
                console.error(`Error removing item from sessionStorage: ${error}`);
            }
        }

        static clearSessionStorage() {
            if (!StorageHelper.isSupported()) {
                console.error('sessionStorage is not supported in this browser');
                return;
            }

            try {
                sessionStorage.clear();
            } catch (error) {
                console.error(`Error clearing sessionStorage: ${error}`);
            }
        }
    }
