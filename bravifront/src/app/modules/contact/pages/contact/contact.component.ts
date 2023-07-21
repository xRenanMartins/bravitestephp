import { Component, OnInit } from '@angular/core';
import { take } from 'rxjs/operators';
import { PersonService } from 'src/app/core/services/person.service';

@Component({
  selector: 'app-contact',
  templateUrl: './contact.component.html',
  styleUrls: ['./contact.component.scss']
})
export class ContactComponent implements OnInit {
  persons: any[] = [];
  isLoading = true;
  params: any;

  constructor(
    private servicePerson: PersonService
    ) {}

  ngOnInit() {
    this.load()
  }

  load() {
    this.isLoading = true;

    this.servicePerson
      .get(this.params)
      .pipe(take(1))
      .subscribe(
        (resp: any) => {
          this.persons = resp.data
          this.isLoading = false;
        },
        (err) => {
          this.isLoading = false;
        }
      );
  }
}
