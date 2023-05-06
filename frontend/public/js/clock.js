var clockInterval         = null;

const clockDivClass       = 'countdown_clock_div';
const insertClockDivAfter = '.Wcmlim_prefloc_sel';

const daysSelector        = '#days';
const hoursSelector       = '#hours';
const minutesSelector     = '#minutes';
const secondsSelector     = '#seconds';

//from WooCommerce-Multi-Locations-Inventory-Management/public/class-wcmlim-public.php
const slugPrefix          = 'wclimloc_';
const locationSelector    = '#select_location';

const stopClock = () =>
{
  if (document.querySelector('.'+clockDivClass))
  {
    document.querySelector('.'+clockDivClass).style.display = 'none';
  }
  if (clockInterval)
  {
    clearInterval(clockInterval);
  }
}

const startClock = (endDate) =>
{
  const countDown = new Date(endDate).getTime();
  
  //expired, ignore countdown
  if (countDown - new Date().getTime() < 0)
  {
    return;
  }
  
  if (!document.querySelector('.'+clockDivClass))
  {
    let div = document.createElement('div');
    div.classList.add(clockDivClass);
    div.innerHTML = config.clock_html;
    document.querySelector(insertClockDivAfter).after(div);
  }

  const second = 1000;
  const minute = second * 60;
  const hour = minute * 60;
  const day = hour * 24;
  
  clockInterval = setInterval(() => {
    const now = new Date().getTime();
    const distance = countDown - now;

    document.querySelector(daysSelector).innerText = Math.floor(distance / (day));
    document.querySelector(hoursSelector).innerText = Math.floor((distance % (day)) / (hour));
    document.querySelector(minutesSelector).innerText = Math.floor((distance % (hour)) / (minute));
    document.querySelector(secondsSelector).innerText = Math.floor((distance % (minute)) / second);

    if (distance < 0)
    {
      window.location.reload();
    }
  }, 0);

  document.querySelector('.'+clockDivClass).style.display = 'block';
}

const checkSelectedLocation = (select) => {
  try
      {
        if (select.options[select.selectedIndex] &&
          select.options[select.selectedIndex].dataset &&
          0 === parseInt(select.options[select.selectedIndex].dataset.lcQty ?? 1))
        {
          let slug = select.options[select.selectedIndex].classList.length > 0 ?
              select.options[select.selectedIndex].classList[0].replace(slugPrefix,'') : '';
              
          if (slug && config.expected_dates[slug])
          {
            startClock(config.expected_dates[slug]);
            return;
          }
        }
        
        stopClock();
      }
      catch (exception) {
        console.log(exception);
      }
}

document.addEventListener('DOMContentLoaded', () =>
{
  if (document.querySelector(locationSelector))
  {
    document.querySelector(locationSelector).addEventListener('change', (e) => {
      checkSelectedLocation(e.target);
    });
    
    //if out of stock, move email button
    const emailButton = document.querySelector('.xoo-wl-btn-container');
    const cartForm = document.querySelector('form.cart');
    if (!emailButton || emailButton.offsetParent === null || !cartForm )
    {
      return;
    }

    setTimeout(() => {
      if (0 === document.querySelector(locationSelector).selectedIndex &&
          1 < document.querySelector(locationSelector).options.length) {
        document.querySelector(locationSelector).selectedIndex = 1;
      }
      document.querySelector(locationSelector).dispatchEvent(new Event('change'));
    }, 500);

    const outerDiv = document.createElement('div');
    outerDiv.classList.add('waitlist-outerdiv');

    const cartFormDiv = document.createElement('div');
    cartForm.after(cartFormDiv);
    cartFormDiv.append(cartForm);

    const emailButtonDiv = document.createElement('div');
    emailButton.after(emailButtonDiv);
    emailButtonDiv.append(emailButton);

    emailButtonDiv.after(outerDiv);
    outerDiv.append(cartFormDiv);
    outerDiv.append(emailButtonDiv);

    const wishlistButton = document.querySelector('.wowmall-wishlist-button').closest('div');
    if (wishlistButton)
    {
      emailButton.style.float = 'left';
      outerDiv.append(wishlistButton);
    }
  }
});