import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { MenuItem } from 'primeng/api';

@Component({
  selector: 'app-menubar',
  templateUrl: './menubar.component.html',
  styleUrls: ['./menubar.component.scss']
})
export class MenubarComponent implements OnInit {
  items: MenuItem[] | undefined;

  constructor(
    private router: Router,
    ){}

  ngOnInit(): void {
    this.items = [
      {
          label: 'Contatos',
          icon: 'pi pi pi-user',
          command: () => {
            this.router.navigateByUrl(`/contacts`);
          },
      }
    ];
  }
  addPerson(){
    this.router.navigateByUrl(`/contacts/add`);
  }
}
